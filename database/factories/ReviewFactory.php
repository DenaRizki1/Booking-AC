<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->withStatus(OrderStatus::SELESAI),
            'user_id' => fn (array $a) => Order::findOrFail($a['order_id'])->user_id,
            'technician_id' => fn (array $a) => Order::findOrFail($a['order_id'])->technician_id,
            'rating' => fake()->randomElement([3, 4, 4, 5, 5, 5]),
            'comment' => fake()->optional()->randomElement([
                'Teknisi ramah dan cepat.', 'AC kembali dingin, terima kasih!', 'Datang tepat waktu.', 'Kerja rapi.',
            ]),
        ];
    }
}
