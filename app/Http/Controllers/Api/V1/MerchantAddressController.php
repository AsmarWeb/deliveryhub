<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        $merchant = Merchant::where(
            'user_id',
            $request->user()->id
        )->firstOrFail();

        return response()->json([
            'data' => $merchant->addresses()
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        $merchant = Merchant::where(
            'user_id',
            $request->user()->id
        )->firstOrFail();

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],

            'country' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:2000'],

            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],

            'delivery_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $address = DB::transaction(
            fn() => $merchant->addresses()->create($data)
        );

        return response()->json([
            'message' => 'Address created successfully.',
            'data' => $address->fresh(),
        ], 201);
    }
}
