<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => fake()->numerify('05########'),
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => strtoupper(fake()->bothify('???-####')),
            'license_number' => fake()->unique()->numerify('########'),
            'status' => 'active',
            'is_available' => true,
        ];
    }
}
