<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Review;
use App\Models\Technician;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->technician = Technician::factory()->create(['review_count' => 2, 'avg_rating' => 4.5]);
    $this->actingAs($this->technician->user);
});

function reviewedOrder(Technician $technician, int $rating, string $comment): Review
{
    $order = Order::factory()->withStatus(OrderStatus::LUNAS)->create(['technician_id' => $technician->id]);

    return Review::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'technician_id' => $technician->id, 'rating' => $rating, 'comment' => $comment]);
}

it('menampilkan rating, jumlah tugas selesai, dan ulasan yang diterima', function () {
    reviewedOrder($this->technician, 5, 'Kerja rapi dan cepat.');
    reviewedOrder($this->technician, 4, 'Ramah.');
    reviewedOrder(Technician::factory()->create(), 1, 'Ulasan teknisi lain');
    Order::factory()->withStatus(OrderStatus::SELESAI)->create(['technician_id' => $this->technician->id]);

    $response = $this->get(route('teknisi.reviews'))->assertOk()
        ->assertSee('4,5')
        ->assertSee('Kerja rapi dan cepat.')
        ->assertSee('Ramah.')
        ->assertDontSee('Ulasan teknisi lain');

    expect($response->viewData('completedCount'))->toBe(3);
});

it('menampilkan keadaan kosong', function () {
    $this->get(route('teknisi.reviews'))->assertSee('Belum ada ulasan');
});

it('navigasi Ulasan aktif di layout teknisi', function () {
    $this->get(route('teknisi.tasks'))->assertSee('href="'.route('teknisi.reviews').'"', false);
});
