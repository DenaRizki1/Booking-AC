<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Isian wizard buat order yang disimpan di session (aman saat halaman di-refresh).
 *
 * @phpstan-type DraftUnit array{ac_brand_id: ?int, ac_capacity_id: ?int, ac_type_id: ?int, service_id: ?int}
 */
class OrderDraft
{
    private const KEY = 'order_draft';

    public const MAX_UNITS = 20;

    public function __construct(private readonly Session $session) {}

    public function addressId(): ?int
    {
        return $this->get()['address_id'];
    }

    /**
     * @return list<DraftUnit>
     */
    public function units(): array
    {
        return $this->get()['units'];
    }

    /**
     * Unit sudah lolos validasi langkah 1 (boleh lanjut ke jadwal).
     */
    public function isReady(): bool
    {
        return $this->get()['ready'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     */
    public function save(?int $addressId, array $units, bool $ready = false): void
    {
        $this->session->put(self::KEY, [
            'address_id' => $addressId,
            'units' => array_slice(array_map(self::sanitizeUnit(...), array_values($units)), 0, self::MAX_UNITS),
            'ready' => $ready,
        ]);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return array{address_id: ?int, units: list<DraftUnit>, ready: bool}
     */
    private function get(): array
    {
        $draft = $this->session->get(self::KEY);

        return [
            'address_id' => isset($draft['address_id']) ? (int) $draft['address_id'] : null,
            'units' => array_values($draft['units'] ?? []),
            'ready' => (bool) ($draft['ready'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $unit
     * @return DraftUnit
     */
    private static function sanitizeUnit(array $unit): array
    {
        $id = fn (string $key) => is_numeric($unit[$key] ?? null) ? (int) $unit[$key] : null;

        return [
            'ac_brand_id' => $id('ac_brand_id'),
            'ac_capacity_id' => $id('ac_capacity_id'),
            'ac_type_id' => $id('ac_type_id'),
            'service_id' => $id('service_id'),
        ];
    }
}
