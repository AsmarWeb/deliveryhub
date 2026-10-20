<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPricingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPricingSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $settings = DeliveryPricingSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'base_fee' => 10,
                'price_per_km' => 1.50,
                'minimum_fee' => 10,
                'maximum_distance_km' => 50,
                'auto_pricing_enabled' => false,
            ]
        );

        return view('admin.pricing.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'base_fee' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'price_per_km' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'minimum_fee' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'maximum_distance_km' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,2'],
            'auto_pricing_enabled' => ['required', 'boolean'],
        ]);

        $settings = DeliveryPricingSetting::query()->firstOrCreate(
            ['id' => 1],
            ['auto_pricing_enabled' => false]
        );

        $settings->update($data);

        return redirect()
            ->route('admin.pricing.edit')
            ->with('success', 'Pricing settings updated successfully.');
    }
}
