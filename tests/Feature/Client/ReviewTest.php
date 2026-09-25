<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Review;
use App\Models\Technician;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->client = User::factory()->create();
    $this->technician = Technician::factory()->create();
    $this->technician->user->update(['name' => 'Rudi Setiawan']);
});

function reviewableOrder(OrderStatus $status = OrderStatus::SELESAI): Order
{
    return Order::factory()->for(test()->client, 'client')->withStatus($status)->create(['technician_id' => test()->technician->id]);
}

it('menampilkan form ulasan sejak order SELESAI', function (OrderStatus $status) {
    $this->actingAs($this->client)->get('/order/'.reviewableOrder($status)->code)->assertSee('Beri ulasan untuk Rudi');
})->with([OrderStatus::SELESAI, OrderStatus::LUNAS]);

it('tidak menampilkan form ulasan sebelum selesai', function () {
    $this->actingAs($this->client)->get('/order/'.reviewableOrder(OrderStatus::DIKERJAKAN)->code)->assertDontSee('Beri ulasan');
});

it('menyimpan ulasan dan menghitung ulang rating teknisi', function () {
    Review::factory()->create(['order_id' => Order::factory()->withStatus(OrderStatus::SELESAI)->create(['technician_id' => $this->technician->id])->id, 'rating' => 4]);
    $order = reviewableOrder();

    $this->actingAs($this->client)
        ->post("/order/{$order->code}/ulasan", ['rating' => 5, 'comment' => 'Teknisi datang tepat waktu dan rapi.'])
        ->assertRedirect("/order/{$order->code}")
        ->assertSessionHas('success', 'Terima kasih atas ulasan Anda!');

    $review = $order->review()->sole();
    expect($review->rating)->toBe(5)
        ->and($review->user_id)->toBe($this->client->id)
        ->and($review->technician_id)->toBe($this->technician->id)
        ->and($this->technician->fresh()->review_count)->toBe(2)
        ->and($this->technician->fresh()->avg_rating)->toBe('4.50');

    $this->actingAs($this->client)->get("/order/{$order->code}")
        ->assertSee('Ulasan Anda')
        ->assertSee('Teknisi datang tepat waktu dan rapi.')
        ->assertDontSee('Beri ulasan');
});

it('hanya mengizinkan 1 ulasan per order', function () {
    $order = reviewableOrder();
    $this->actingAs($this->client)->post("/order/{$order->code}/ulasan", ['rating' => 5]);

    $this->actingAs($this->client)
        ->post("/order/{$order->code}/ulasan", ['rating' => 1])
        ->assertSessionHas('error', 'Order ini belum bisa atau sudah diberi ulasan.');

    expect(Review::count())->toBe(1)
        ->and($this->technician->fresh()->avg_rating)->toBe('5.00');
});

it('menolak ulasan sebelum order selesai', function () {
    $order = reviewableOrder(OrderStatus::DIKERJAKAN);

    $this->actingAs($this->client)->post("/order/{$order->code}/ulasan", ['rating' => 5])->assertSessionHas('error');
    expect(Review::count())->toBe(0);
});

it('memvalidasi rating 1–5', function (mixed $rating) {
    $this->actingAs($this->client)
        ->post('/order/'.reviewableOrder()->code.'/ulasan', ['rating' => $rating])
        ->assertSessionHasErrors('rating');
})->with([0, 6, 'bagus', null]);

it('melarang client lain memberi ulasan', function () {
    $this->actingAs(User::factory()->create())
        ->post('/order/'.reviewableOrder()->code.'/ulasan', ['rating' => 1])
        ->assertForbidden();
});
