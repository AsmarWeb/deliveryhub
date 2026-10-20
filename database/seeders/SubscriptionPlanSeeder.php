<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'monthly_price' => '0.00',
                'commission_rate' => '15.00',
                'monthly_delivery_limit' => 30,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'monthly_price' => '99.00',
                'commission_rate' => '10.00',
                'monthly_delivery_limit' => 300,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'monthly_price' => '299.00',
                'commission_rate' => '7.00',
                'monthly_delivery_limit' => 1500,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'monthly_price' => '0.00',
                'commission_rate' => '5.00',
                'monthly_delivery_limit' => null,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, [
                    'currency' => 'AED',
                    'is_active' => true,
                ])
            );
        }
    }
}
