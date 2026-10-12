<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'label' => 'Test Address',
            'contact_name' => fake()->name(),
            'contact_phone' => '0501234567',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => 'Business Bay',
            'street' => 'Test Street',
            'building' => 'Test Building',
            'unit' => '101',
            'address_line' => 'Business Bay, Dubai, UAE',
            'latitude' => 25.1850,
            'longitude' => 55.2700,
            'delivery_instructions' => null,
        ];
    }
}
