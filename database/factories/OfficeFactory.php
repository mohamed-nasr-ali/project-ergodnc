<?php

namespace Database\Factories;

use App\Enums\OfficeApprovalStatus;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory
{
    protected $model=Office::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'lat' => $this->faker->latitude,
            'lng' => $this->faker->longitude,
            'address_line1' => $this->faker->address,
            'approval_status' => OfficeApprovalStatus::APPROVAL_APPROVED,
            'hidden' => false,
            'price_per_day' => $this->faker->numberBetween(1_000, 2_000),
            'monthly_discount' => 0
        ];
    }

    public function pending(): OfficeFactory
    {
        return  $this->state(['approval_status' => OfficeApprovalStatus::APPROVAL_PENDING]);
    }
    public function hidden(): OfficeFactory
    {
        return  $this->state(['hidden' => true]);
    }
}
