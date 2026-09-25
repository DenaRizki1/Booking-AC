<?php

namespace Database\Factories;

use App\Enums\PhotoKind;
use App\Models\OrderItem;
use App\Models\OrderPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: foto SESUDAH untuk satu unit.
 *
 * @extends Factory<OrderPhoto>
 */
class OrderPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'order_id' => fn (array $a) => OrderItem::findOrFail($a['order_item_id'])->order_id,
            'kind' => PhotoKind::SESUDAH,
            'path' => 'orders/'.Str::random(16).'.jpg',
            'uploaded_by' => User::factory()->teknisi(),
        ];
    }

    public function kind(PhotoKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }
}
