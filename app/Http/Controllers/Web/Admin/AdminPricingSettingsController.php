<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPricingSetting;
use App\Support\UaeAddressFields;
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
            'emirate_pricing_enabled' => ['sometimes', 'boolean'],
            'emirate_rates' => ['sometimes', 'array:'.implode(',', UaeAddressFields::EMIRATES)],
            'emirate_rates.*' => ['array:'.implode(',', UaeAddressFields::EMIRATES)],
            'emirate_rates.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
        ]);

        $settings = DeliveryPricingSetting::query()->firstOrCreate(
            ['id' => 1],
            ['auto_pricing_enabled' => false]
        );

        if (array_key_exists('emirate_rates', $data)) {
            // Keep zero as an explicit rate; blank cells mean no automatic quote.
            $data['emirate_rates'] = array_map(
                fn ($row) => array_map(fn ($value) => number_format((float) $value, 2, '.', ''),
                    array_filter($row, fn ($value) => $value !== null)),
                $data['emirate_rates']
            );
        }
        $settings->update($data);

        return redirect()
            ->route('admin.pricing.edit')
            ->with('success', 'Pricing settings updated successfully.');
    }
}
