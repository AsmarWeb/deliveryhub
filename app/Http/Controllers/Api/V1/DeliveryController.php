<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $query = Delivery::query();
        } elseif ($user->hasRole('merchant')) {
            $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

            $query = Delivery::where('merchant_id', $merchant->id);
        } else {
            abort(403, 'Unauthorized.');
        }

        $deliveries = $query
            ->with(['pickupAddress', 'dropoffAddress'])
            ->latest()
            ->paginate(15);

        return response()->json($deliveries);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->hasRole('merchant'), 403);

        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

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
                    ->where(function ($query) use ($merchant) {
                        $query->where('merchant_id', $merchant->id);
                    }),
            ],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'package_type' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'special_instructions' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $delivery = DB::transaction(function () use ($merchant, $user, $data) {
            $delivery = Delivery::create([
                ...$data,
                'merchant_id' => $merchant->id,
                'tracking_number' => 'DH-' . Str::upper((string) Str::ulid()),
                'status' => DeliveryStatus::Draft,
                'currency' => 'AED',
            ]);

            $delivery->statusHistories()->create([
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => DeliveryStatus::Draft->value,
                'reason' => 'Delivery created',
            ]);

            return $delivery;
        });

        return response()->json([
            'message' => 'Delivery created successfully.',
            'data' => $delivery->load([
                'pickupAddress',
                'dropoffAddress',
            ]),
        ], 201);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('merchant')) {
            $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

            abort_unless($delivery->merchant_id === $merchant->id, 403);
        } elseif (! $user->hasRole('admin')) {
            abort(403);
        }

        return response()->json([
            'data' => $delivery->load([
                'merchant',
                'pickupAddress',
                'dropoffAddress',
                'assignments',
                'statusHistories',
            ]),
        ]);
    }
}
