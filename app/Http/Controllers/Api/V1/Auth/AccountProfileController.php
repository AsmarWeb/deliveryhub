<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountProfileController extends Controller
{
    private function profile(Request $request): Merchant|Driver
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->first();
        $driver = Driver::where('user_id', $request->user()->id)->first();
        abort_if($merchant && $driver, 409, 'Multiple application profiles found.');
        abort_unless($merchant || $driver, 404, 'Application profile not found.');
        return $merchant ?? $driver;
    }

    public function show(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $merchant = $profile instanceof Merchant;
        $fields = $merchant
            ? ['business_name', 'business_phone', 'business_email', 'trade_license_number', 'tax_number']
            : ['phone', 'vehicle_type', 'vehicle_plate', 'license_number'];
        $required = $merchant ? ['business_name', 'business_phone'] : $fields;
        $missing = array_values(array_filter($required, fn ($field) => blank($profile->{$field})));
        return response()->json(['data' => [
            'type' => $merchant ? 'merchant' : 'driver',
            'status' => $profile->status,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'details' => $profile->only($fields),
            'missing_fields' => $missing,
            'details_editable' => $profile->status === 'pending',
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $merchant = $profile instanceof Merchant;
        $rules = ['name' => ['sometimes', 'required', 'string', 'max:255']];
        $rules += $merchant ? [
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_phone' => ['sometimes', 'required', 'regex:/^\+971[0-9]{8,9}$/'],
            'business_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'trade_license_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ] : [
            'phone' => ['sometimes', 'required', 'regex:/^\+971[0-9]{8,9}$/'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'vehicle_plate' => ['sometimes', 'nullable', 'string', 'max:100'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
        foreach (['status', 'user_id', 'roles', 'is_available', 'stripe_id', 'email'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($profile, $request, $data) {
            $locked = $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $details = array_diff_key($data, ['name' => true]);
            // Approved or inactive verification data requires administrative review.
            if ($locked->status !== 'pending') {
                foreach ($details as $key => $value) {
                    if (($locked->{$key} ?? '') !== ($value ?? '')) {
                        throw ValidationException::withMessages([$key => 'Contact support to change reviewed account details.']);
                    }
                }
            }
            $locked->fill($details)->save();
            if (isset($data['name'])) $request->user()->update(['name' => $data['name']]);
        });
        return $this->show($request);
    }
}
