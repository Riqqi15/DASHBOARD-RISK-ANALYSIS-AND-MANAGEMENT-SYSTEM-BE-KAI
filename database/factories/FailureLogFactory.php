<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FailureLog> */
class FailureLogFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $resolvedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(15, 480).' minutes');

        return [
            'asset_id' => Asset::factory(),
            'spare_part_id' => null,
            'created_by' => User::factory()->pusat(),
            'source_key' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'location' => fake()->city(),
            'resort' => fake()->optional()->bothify('Resor #.#'),
            'qc' => fake()->optional()->word(),
            'failure_event' => fake()->sentence(4),
            'cause' => fake()->sentence(),
            'action_taken' => fake()->sentence(),
            'started_at' => $startedAt,
            'resolved_at' => $resolvedAt,
            'downtime_minutes' => (int) (($resolvedAt->getTimestamp() - $startedAt->getTimestamp()) / 60),
            'spare_part_replaced' => false,
            'spare_part_quantity' => null,
            'vandalism' => false,
        ];
    }
}
