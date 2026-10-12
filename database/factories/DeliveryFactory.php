<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),

            'pickup_address_id' => Address::factory(),
            'dropoff_address_id' => Address::factory(),

            'tracking_number' => 'DH-' . Str::upper(Str::random(12)),
            'status' => DeliveryStatus::cases()[0]->value,

            'recipient_name' => fake()->name(),
            'recipient_phone' => '0501234567',

            'package_type' => 'parcel',
            'weight' => 1.50,

            'currency' => 'AED',
            'special_instructions' => null,
        ];
    }
}
