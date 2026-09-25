<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PhotoKind;
use App\Exceptions\OrderPlacementException;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Klaim garansi: order baru jenis GARANSI (Rp0) untuk unit yang masih bergaransi (WORKFLOW bagian 7).
 */
class WarrantyClaimService
{
    /** Status order klaim yang dianggap masih berjalan (unit tidak bisa diklaim lagi). */
    private const FINISHED = [OrderStatus::DITOLAK, OrderStatus::DIBATALKAN, OrderStatus::SELESAI, OrderStatus::LUNAS];

    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly OrderCodeGenerator $codes,
        private readonly OrderStatusService $statuses,
    ) {}

    /**
     * Unit order ini yang masih bisa diklaim: order REGULER milik client yang sudah SELESAI/LUNAS,
     * garansi belum lewat, dan belum ada klaim yang sedang berjalan (ERD bagian 8).
     *
     * @return Collection<int, OrderItem>
     */
    public function claimableItems(Order $order): Collection
    {
        if ($order->order_type !== OrderType::REGULER || ! in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true)) {
            return new Collection;
        }

        return $order->items()
            ->whereDate('warranty_until', '>=', today())
            ->whereDoesntHave('claims', fn (Builder $claim) => $claim->whereHas('order', fn (Builder $o) => $o->whereNotIn('status', self::FINISHED)))
            ->with('service:id,requires_helper')
            ->get();
    }

    /**
     * Unit bergaransi aktif milik client (untuk kartu "Garansi aktif" di beranda), terlama berlaku dulu.
     */
    public function latestClaimableItem(User $client): ?OrderItem
    {
        return OrderItem::query()
            ->whereDate('warranty_until', '>=', today())
            ->whereHas('order', fn (Builder $order) => $order
                ->where('user_id', $client->id)
                ->where('order_type', OrderType::REGULER)
                ->whereIn('status', [OrderStatus::SELESAI, OrderStatus::LUNAS]))
            ->whereDoesntHave('claims', fn (Builder $claim) => $claim->whereHas('order', fn (Builder $o) => $o->whereNotIn('status', self::FINISHED)))
            ->with('order:id,code')
            ->orderByDesc('warranty_until')
            ->first();
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array{minutes: int, requires_helper: bool}
     */
    public function workload(Collection $items): array
    {
        return [
            'minutes' => (int) $items->sum('est_minutes'),
            'requires_helper' => $items->contains(fn (OrderItem $item) => (bool) $item->service?->requires_helper),
        ];
    }

    /**
     * @param  list<int>  $itemIds
     *
     * @throws OrderPlacementException
     */
    public function claim(User $client, Order $parent, array $itemIds, CarbonImmutable $start, string $complaint, ?UploadedFile $photo = null): Order
    {
        return DB::transaction(function () use ($client, $parent, $itemIds, $start, $complaint, $photo) {
            /** @var CompanySettings $settings */
            $settings = CompanySettings::query()->lockForUpdate()->firstOrFail();

            if ($parent->user_id !== $client->id) {
                throw new OrderPlacementException('items', 'Order tidak ditemukan.');
            }

            $items = $this->claimableItems($parent)->whereIn('id', $itemIds)->values();
            if ($items->isEmpty() || $items->count() !== count(array_unique($itemIds))) {
                throw new OrderPlacementException('items', 'Pilih unit yang masih dalam masa garansi dan belum diklaim.');
            }

            ['minutes' => $minutes, 'requires_helper' => $needsHelper] = $this->workload($items);
            $available = collect($this->schedule->availableSlots($start, $minutes, $needsHelper, $settings))
                ->contains(fn ($slot) => $slot->start->equalTo($start));

            if (! $available) {
                throw new OrderPlacementException('time', "Jam {$start->format('H:i')} pada ".$start->translatedFormat('j F').' tidak tersedia. Silakan pilih jam lain.');
            }

            [$end, $blockedUntil] = $this->schedule->window($start, $minutes, $settings);

            $claim = Order::create([
                'code' => $this->codes->next(),
                'order_type' => OrderType::GARANSI,
                'parent_order_id' => $parent->id,
                'user_id' => $client->id,
                'customer_address_id' => $parent->customer_address_id,
                'address_snapshot' => $parent->address_snapshot,
                'lat' => $parent->lat,
                'lng' => $parent->lng,
                'distance_km' => $parent->distance_km,
                'requires_helper' => $needsHelper,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'blocked_until' => $blockedUntil,
                'total_est_minutes' => $minutes,
                'est_price_min' => 0,
                'est_price_max' => 0,
                'transport_fee' => 0,
                'customer_note' => trim($complaint),
                'status' => OrderStatus::MENUNGGU,
            ]);

            foreach ($items as $index => $item) {
                $claim->items()->create([
                    'claimed_item_id' => $item->id,
                    'service_id' => $item->service_id,
                    'service_price_id' => $item->service_price_id,
                    'ac_brand_id' => $item->ac_brand_id,
                    'ac_capacity_id' => $item->ac_capacity_id,
                    'ac_type_id' => $item->ac_type_id,
                    'service_name' => $item->service_name,
                    'unit_label' => $item->unit_label,
                    'est_price_min' => 0,
                    'est_price_max' => 0,
                    'est_minutes' => $item->est_minutes,
                    'warranty_days' => $item->warranty_days,
                    'sort_order' => $index + 1,
                ]);
            }

            if ($photo !== null) {
                $claim->photos()->create([
                    'kind' => PhotoKind::KELUHAN,
                    'path' => $photo->store("orders/{$claim->code}", 'public'),
                    'uploaded_by' => $client->id,
                ]);
            }

            $this->statuses->recordCreated($claim, $client, 'Klaim garansi dari '.$parent->code);

            return $claim;
        });
    }
}
