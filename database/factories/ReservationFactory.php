<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = now()->addHour();

        return [
            'resource_id' => Resource::factory(),
            'reservation_number' => $this->faker->unique()->numberBetween(100000, 99999999),
            'units' => 1,
            'start_time' => $start,
            'end_time' => (clone $start)->addHour(),
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->addMinutes(2),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Confirmed,
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Cancelled,
            'expires_at' => null,
        ]);
    }
}
