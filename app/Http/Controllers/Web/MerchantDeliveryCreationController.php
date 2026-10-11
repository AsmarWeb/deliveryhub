<?php

namespace App\Http\Controllers\Web;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MerchantDeliveryCreationController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        return Merchant::where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function create(Request $request): View
    {
        $merchant = $this->merchant($request);

        $addresses = Address::query()
            ->where('merchant_id', $merchant->id)
            ->orderBy('id')
            ->get();

        return view('merchant.deliveries.create', [
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $merchant = $this->merchant($request);

        $data = $request->validate([
            'pickup_address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('merchant_id', $merchant->id),
            ],
            'dropoff_address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('merchant_id', $merchant->id),
            ],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'package_type' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'special_instructions' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $delivery = DB::transaction(function () use ($data, $merchant, $request) {
            $delivery = Delivery::create([
                ...$data,
                'merchant_id' => $merchant->id,
                'tracking_number' => 'DH-' . Str::upper((string) Str::ulid()),
                'status' => DeliveryStatus::Draft,
                'currency' => 'AED',
            ]);

            $delivery->statusHistories()->create([
                'user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => DeliveryStatus::Draft->value,
                'reason' => 'Delivery created',
            ]);

            return $delivery;
        });

        return redirect()
            ->route('merchant.deliveries.index')
            ->with(
                'success',
                'Delivery ' . $delivery->tracking_number . ' created successfully.'
            );
    }
}
