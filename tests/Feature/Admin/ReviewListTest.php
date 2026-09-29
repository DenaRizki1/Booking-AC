<?php

use App\Enums\OrderStatus;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\Review;
use App\Models\Technician;
use App\Models\User;

beforeEach(function () {
    CompanySettings::factory()->create();
    $this->actingAs(User::factory()->admin()->create());
    $this->rudi = Technician::factory()->create();
    $this->bima = Technician::factory()->create();
});

function reviewFor(Technician $technician, int $rating, string $comment): Review
{
    $order = Order::factory()->withStatus(OrderStatus::LUNAS)->create(['technician_id' => $technician->id]);

    return Review::factory()->create(['order_id' => $order->id, 'user_id' => $order->user_id, 'technician_id' => $technician->id, 'rating' => $rating, 'comment' => $comment]);
}

it('menampilkan semua ulasan dengan rata-rata dan distribusi', function () {
    reviewFor($this->rudi, 5, 'Rapi sekali.');
    reviewFor($this->rudi, 4, 'Cukup cepat.');
    reviewFor($this->bima, 2, 'Terlambat datang.');

    $response = $this->get(route('admin.reviews.index'))->assertOk()
        ->assertSee(['Rapi sekali.', 'Cukup cepat.', 'Terlambat datang.'])
        ->assertSee('3,7')
        ->assertSee('dari 3 ulasan');

    expect($response->viewData('distribution')->all())->toEqual([2 => 1, 4 => 1, 5 => 1]);
});

it('memfilter per teknisi', function () {
    reviewFor($this->rudi, 5, 'Rapi sekali.');
    reviewFor($this->bima, 2, 'Terlambat datang.');

    $this->get(route('admin.reviews.index', ['technician' => $this->bima->id]))
        ->assertSee('Terlambat datang.')
        ->assertDontSee('Rapi sekali.')
        ->assertSee('dari 1 ulasan');
});

it('memfilter per rating', function () {
    reviewFor($this->rudi, 5, 'Rapi sekali.');
    reviewFor($this->rudi, 3, 'Biasa saja.');

    $this->get(route('admin.reviews.index', ['rating' => 3]))
        ->assertSee('Biasa saja.')
        ->assertDontSee('Rapi sekali.');
});

it('menampilkan keadaan kosong dan menolak rating tidak valid', function () {
    $this->get(route('admin.reviews.index'))->assertSee('Belum ada ulasan');
    $this->get(route('admin.reviews.index', ['rating' => 9]))->assertSessionHasErrors('rating');
});
