<?php

use App\Enums\OrderStatus;

it('menampilkan katalog /dev/ui di environment local', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/dev/ui')
        ->assertOk()
        ->assertSee('UI Kit')
        ->assertSee('Dalam Perjalanan');
});

it('menyembunyikan /dev/ui di luar environment local', function () {
    $this->get('/dev/ui')->assertNotFound();
});

it('merender badge sesuai status order', function () {
    $html = (string) $this->blade('<x-badge :status="$status" />', ['status' => OrderStatus::MENUNGGU]);

    expect($html)->toContain('Menunggu')->toContain(OrderStatus::MENUNGGU->color());

    $this->blade('<x-badge status="LUNAS" />')->assertSee('Lunas');
});

it('menautkan label ke input dan menampilkan error validasi', function () {
    $this->withViewErrors(['phone' => 'Nomor HP tidak valid.']);

    $view = $this->blade('<x-input name="phone" label="No. HP" />');

    $view->assertSee('for="f-phone"', false)
        ->assertSee('id="f-phone"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="f-phone-error"', false)
        ->assertSee('Nomor HP tidak valid.');
});

it('menandai opsi select yang dipilih', function () {
    $this->blade('<x-select name="pk" :options="[\'1\' => \'1 PK\', \'2\' => \'2 PK\']" value="2" />')
        ->assertSee('<option value="2" selected>2 PK</option>', false);
});

it('menampilkan pesan flash dari session', function () {
    session()->flash('success', 'Data tersimpan.');

    $this->blade('<x-flash />')
        ->assertSee('Data tersimpan.')
        ->assertSee('role="status"', false);
});

it('memberi ukuran target sentuh minimal 44px pada tombol', function (string $size) {
    $this->blade('<x-button size="'.$size.'">OK</x-button>')
        ->assertSee(match ($size) {
            'sm' => 'min-h-11',
            'md' => 'min-h-12',
            'lg' => 'min-h-14',
        });
})->with(['sm', 'md', 'lg']);

it('merender ikon Lucide sebagai SVG', function () {
    $this->blade('<x-icon name="map-pin" class="size-4" />')
        ->assertSee('<svg', false)
        ->assertSee('aria-hidden="true"', false);
});
