<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class SelfRegistrationController extends Controller
{
    public function merchant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'business_name' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'regex:/^\\+971[0-9]{8,9}$/'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'trade_license_number' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->addRole('merchant');
            Merchant::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'business_phone' => $data['business_phone'],
                'business_email' => $data['business_email'] ?? null,
                'trade_license_number' => $data['trade_license_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'status' => 'pending',
            ]);
            return $user;
        });

        // No token is issued until the merchant's onboarding policy is enforced.
        return response()->json([
            'message' => 'Merchant application received.',
            'user_id' => $user->id,
            'status' => 'pending',
        ], 201);
    }

    public function driver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['required', 'regex:/^\\+971[0-9]{8,9}$/'],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'vehicle_plate' => ['required', 'string', 'max:100'],
            'license_number' => ['required', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            // Deliberately do not assign the operational 'driver' role here.
            Driver::create([
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'vehicle_type' => $data['vehicle_type'],
                'vehicle_plate' => $data['vehicle_plate'],
                'license_number' => $data['license_number'],
                'status' => 'pending',
                'is_available' => false,
            ]);
            return $user;
        });

        return response()->json([
            'message' => 'Driver application received.',
            'user_id' => $user->id,
            'status' => 'pending',
        ], 201);
    }
}
