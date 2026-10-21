<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DeliveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'merchant@deliveryhub.test'],
            [
                'name' => 'Raslo Demo Merchant',
                'password' => Hash::make('DemoMerchant@2026!'),
            ]
        );

        if (! $user->hasRole('merchant')) {
            $user->addRole('merchant');
        }

        $merchant = Merchant::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'Raslo Demo Store',
                'business_email' => 'merchant@deliveryhub.test',
                'business_phone' => '+971500000001',
                'status' => 'active',
            ]
        );

        Address::updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'label' => 'Main Warehouse',
            ],
            [
                'contact_name' => 'Warehouse Manager',
                'contact_phone' => '+971500000001',
                'country' => 'United Arab Emirates',
                'city' => 'Dubai',
                'area' => 'Al Quoz',
                'address_line' => 'Al Quoz Industrial Area, Dubai',
                'latitude' => 25.1412,
                'longitude' => 55.2324,
            ]
        );

        Address::updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'label' => 'Customer Address',
            ],
            [
                'contact_name' => 'Demo Customer',
                'contact_phone' => '+971500000002',
                'country' => 'United Arab Emirates',
                'city' => 'Dubai',
                'area' => 'Business Bay',
                'address_line' => 'Business Bay, Dubai',
                'latitude' => 25.1860,
                'longitude' => 55.2720,
            ]
        );

        $this->command?->info(
            'Demo merchant and addresses created successfully.'
        );
    }
}
