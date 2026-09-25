<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\Technician;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ulasan client (1× per order, sejak SELESAI) + hitung ulang rating teknisi.
 */
class ReviewService
{
    public function canReview(Order $order, User $client): bool
    {
        return $order->user_id === $client->id
            && in_array($order->status, [OrderStatus::SELESAI, OrderStatus::LUNAS], true)
            && $order->technician_id !== null
            && ! $order->review()->exists();
    }

    /**
     * @throws DomainException bila order belum bisa/sudah diulas
     */
    public function submit(Order $order, User $client, int $rating, ?string $comment = null): Review
    {
        return DB::transaction(function () use ($order, $client, $rating, $comment) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $this->canReview($locked, $client)) {
                throw new DomainException('Order ini belum bisa atau sudah diberi ulasan.');
            }

            $review = $locked->review()->create([
                'user_id' => $client->id,
                'technician_id' => $locked->technician_id,
                'rating' => max(1, min(5, $rating)),
                'comment' => filled($comment) ? trim((string) $comment) : null,
            ]);

            $this->refreshRating($review->technician()->firstOrFail());

            return $review;
        });
    }

    public function refreshRating(Technician $technician): void
    {
        $stats = $technician->reviews()->toBase()->selectRaw('COUNT(*) AS total, AVG(rating) AS average')->first();

        $technician->update([
            'review_count' => (int) ($stats->total ?? 0),
            'avg_rating' => round((float) ($stats->average ?? 0), 2),
        ]);
    }
}
