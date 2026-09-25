<?php

use App\Services\OrderCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

it('membuat kode ORD-YYMM-NNNN berurutan', function () {
    $generator = new OrderCodeGenerator;
    $october = CarbonImmutable::parse('2026-10-05 09:00');

    expect($generator->next($october))->toBe('ORD-2610-0001')
        ->and($generator->next($october))->toBe('ORD-2610-0002')
        ->and($generator->next($october->addDays(20)))->toBe('ORD-2610-0003');
});

it('mereset nomor urut tiap bulan', function () {
    $generator = new OrderCodeGenerator;

    $generator->next(CarbonImmutable::parse('2026-10-31 23:59'));
    $generator->next(CarbonImmutable::parse('2026-10-31 23:59'));

    expect($generator->next(CarbonImmutable::parse('2026-11-01 00:00')))->toBe('ORD-2611-0001')
        ->and($generator->next(CarbonImmutable::parse('2026-10-15')))->toBe('ORD-2610-0003');
});

it('memakai waktu sekarang (Asia/Jakarta) secara default', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30', 'Asia/Jakarta'));

    expect((new OrderCodeGenerator)->next())->toBe('ORD-2612-0001');
});

it('tidak menghasilkan kode sama saat dibuat bersamaan', function () {
    // Proses terpisah melakukan commit sendiri, jadi pakai periode khusus lalu bersihkan lewat koneksi lain.
    config(['database.connections.concurrency' => config('database.connections.mysql')]);
    $cleanup = fn () => DB::connection('concurrency')->table('order_code_sequences')->where('period', '9901')->delete();
    $cleanup();

    $script = 'for ($i = 0; $i < 5; $i++) { echo app(App\Services\OrderCodeGenerator::class)->next(Carbon\CarbonImmutable::parse("2099-01-15")), PHP_EOL; }';

    try {
        $results = Process::concurrently(function ($pool) use ($script) {
            foreach (range(1, 8) as $worker) {
                $pool->path(base_path())
                    ->env(['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => config('database.connections.mysql.database')])
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute='.$script]);
            }
        });
    } finally {
        $cleanup();
    }

    $codes = $results->collect()
        ->each(fn ($result) => expect($result->successful())->toBeTrue($result->errorOutput()))
        ->flatMap(fn ($result) => preg_split('/\R/', trim($result->output())))
        ->filter(fn (string $line) => str_starts_with($line, 'ORD-'))
        ->values();

    expect($codes)->toHaveCount(40)
        ->and($codes->unique())->toHaveCount(40)
        ->and($codes->sort()->values()->all())->toBe(
            array_map(fn (int $n) => sprintf('ORD-9901-%04d', $n), range(1, 40))
        );
})->group('concurrency');
