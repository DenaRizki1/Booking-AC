<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CompanySettings::factory()->create(['bank_name' => 'BCA', 'bank_account_no' => '1234567890']);
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Kasir']);
    $this->actingAs($this->admin);
    $this->order = Order::factory()->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 250000]);
});

it('menampilkan form konfirmasi pembayaran untuk order SELESAI', function () {
    $this->get(route('admin.orders.show', $this->order))
        ->assertOk()
        ->assertSee('Konfirmasi lunas')
        ->assertSee('Rp250.000')
        ->assertSee('BCA 1234567890');
});

it('mengonfirmasi pembayaran tunai menjadi LUNAS', function () {
    $this->post(route('admin.orders.payment', $this->order), ['payment_method' => 'TUNAI'])
        ->assertRedirect(route('admin.orders.show', $this->order))
        ->assertSessionHas('success', 'Pembayaran dikonfirmasi. Order LUNAS.');

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::LUNAS)
        ->and($order->payment_method)->toBe(PaymentMethod::TUNAI)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->paid_confirmed_by)->toBe($this->admin->id)
        ->and($order->payment_proof)->toBeNull()
        ->and($order->statusLogs()->latest('id')->first()->note)->toBe('Dibayar Tunai Rp250.000');

    $this->get(route('admin.orders.show', $this->order))->assertSee('Lunas')->assertSee('Admin Kasir')->assertDontSee('Konfirmasi lunas');
});

it('menyimpan bukti transfer di disk privat dan hanya admin yang bisa membukanya', function () {
    Storage::fake('local');

    $this->post(route('admin.orders.payment', $this->order), [
        'payment_method' => 'TRANSFER',
        'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    expect($order->payment_method)->toBe(PaymentMethod::TRANSFER);
    Storage::disk('local')->assertExists($order->payment_proof);

    $this->get(route('admin.orders.payment-proof', $order))->assertOk();
    $this->actingAs($order->client)->get(route('admin.orders.payment-proof', $order))->assertForbidden();
});

it('memvalidasi metode dan bukti bayar', function (array $payload, string $field) {
    $this->from(route('admin.orders.show', $this->order))
        ->post(route('admin.orders.payment', $this->order), $payload)
        ->assertSessionHasErrorsIn('payment', $field);

    expect($this->order->fresh()->status)->toBe(OrderStatus::SELESAI);
})->with([
    'tanpa metode' => [[], 'payment_method'],
    'metode asing' => [['payment_method' => 'QRIS'], 'payment_method'],
    'bukti bukan gambar' => [['payment_method' => 'TRANSFER', 'payment_proof' => UploadedFile::fake()->create('a.exe', 10)], 'payment_proof'],
]);

it('hanya order SELESAI yang bisa dikonfirmasi', function () {
    Storage::fake('local');
    $order = Order::factory()->withStatus(OrderStatus::DIKERJAKAN)->create();

    $this->post(route('admin.orders.payment', $order), ['payment_method' => 'TRANSFER', 'payment_proof' => UploadedFile::fake()->image('b.png')])
        ->assertSessionHas('error');

    expect($order->fresh()->status)->toBe(OrderStatus::DIKERJAKAN);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('order tanpa tagihan tidak butuh konfirmasi pembayaran', function () {
    $free = Order::factory()->withStatus(OrderStatus::SELESAI)->create(['grand_total' => 0]);

    $this->get(route('admin.orders.show', $free))->assertSee('tidak perlu konfirmasi pembayaran')->assertDontSee('Konfirmasi lunas');
    $this->post(route('admin.orders.payment', $free), ['payment_method' => 'TUNAI'])->assertSessionHas('error');

    expect($free->fresh()->status)->toBe(OrderStatus::SELESAI);
});
