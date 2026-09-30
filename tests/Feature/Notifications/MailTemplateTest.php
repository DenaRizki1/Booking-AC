<?php

use App\Models\CompanySettings;
use Illuminate\Notifications\Messages\MailMessage;

it('memakai tema email ber-branding dan teks Bahasa Indonesia', function () {
    CompanySettings::factory()->create(['company_name' => 'SejukAC', 'phone' => '081234567890']);

    $html = (string) (new MailMessage)
        ->greeting('Halo, Dena!')
        ->line('Order sudah ditugaskan.')
        ->action('Lihat order', 'https://booking-ac.test/order')
        ->render();

    expect(config('mail.markdown.theme'))->toBe('booking')
        ->and($html)->toContain('SejukAC')
        ->toContain('081234567890')
        ->toContain('Halo, Dena!')
        ->toContain('Salam,')
        ->toContain('Jika tombol "Lihat order" tidak bisa diklik')
        ->toContain('Email ini dikirim otomatis')
        ->toContain('#1d63ed')
        ->not->toContain('Laravel');
});
