<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    protected $model=Reservation::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'price' => $this->faker->numberBetween(10_00, 20_00),
            'status' => ReservationStatus::STATUS_ACTIVE,
            'start_date' => now()->addDay(1)->format('Y-m-d'),
            'end_date' => now()->addDay(5)->format('Y-m-d'),
        ];
    }

    public function active()
    {
        return $this->state([ 'status' => ReservationStatus::STATUS_ACTIVE]);
    }
    public function cancelled()
    {
        return $this->state([ 'status' => ReservationStatus::STATUS_CANCELLED]);
    }
}
