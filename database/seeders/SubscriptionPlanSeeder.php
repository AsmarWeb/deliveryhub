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
                'max_active_automations' => 0,
                'slug' => 'starter',
                'monthly_price' => '0.00',
                'commission_rate' => '15.00',
                'monthly_delivery_limit' => 30,
            ],
            [
                'name' => 'Growth',
                'max_active_automations' => 5,
                'slug' => 'growth',
                'monthly_price' => '99.00',
                'commission_rate' => '10.00',
                'monthly_delivery_limit' => 300,
            ],
            [
                'name' => 'Business',
                'max_active_automations' => 20,
                'slug' => 'business',
                'monthly_price' => '299.00',
                'commission_rate' => '7.00',
                'monthly_delivery_limit' => 1500,
            ],
            [
                'name' => 'Enterprise',
                'max_active_automations' => 50,
                'slug' => 'enterprise',
                'monthly_price' => '0.00',
                'commission_rate' => '5.00',
                'monthly_delivery_limit' => null,
            ],
        ];

        foreach ($plans as $plan) {
            // Retain an operator-configured Enterprise contract price across re-seeding.
            if ($plan['slug'] === 'enterprise' && SubscriptionPlan::where('slug', 'enterprise')->exists()) {
                unset($plan['monthly_price']);
            }
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
