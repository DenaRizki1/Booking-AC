<?php

namespace Database\Factories;

use App\Models\AcBrand;
use App\Models\AcCapacity;
use App\Models\AcType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priceMin = fake()->numberBetween(15, 40) * 5000;

        return [
            'order_id' => Order::factory(),
            'claimed_item_id' => null,
            'service_id' => Service::factory(),
            'service_price_id' => null,
            'ac_brand_id' => AcBrand::factory(),
            'ac_capacity_id' => AcCapacity::factory(),
            'ac_type_id' => AcType::factory(),
            'service_name' => fn (array $a) => Service::withTrashed()->findOrFail($a['service_id'])->name,
            'unit_label' => fn (array $a) => implode(' · ', [
                AcBrand::findOrFail($a['ac_brand_id'])->name,
                AcCapacity::findOrFail($a['ac_capacity_id'])->label,
                AcType::findOrFail($a['ac_type_id'])->name,
            ]),
            'est_price_min' => $priceMin,
            'est_price_max' => $priceMin + fake()->numberBetween(1, 6) * 5000,
            'est_minutes' => fake()->randomElement([60, 90, 120]),
            'final_price' => null,
            'price_note' => null,
            'work_note' => null,
            'warranty_days' => 7,
            'warranty_until' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Unit dengan harga & durasi dari satu sel matriks harga.
     */
    public function forPrice(ServicePrice $price): static
    {
        return $this->state(fn () => [
            'service_id' => $price->service_id,
            'service_price_id' => $price->getKey(),
            'ac_capacity_id' => $price->ac_capacity_id,
            'ac_type_id' => $price->ac_type_id,
            'est_price_min' => $price->price_min,
            'est_price_max' => $price->price_max,
            'est_minutes' => $price->est_minutes,
        ]);
    }
}
