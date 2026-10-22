<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationDeliveryOrder;
use App\Services\Integrations\StoreDeliveryService;
use App\Support\UaeAddressFields;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreDeliveryController extends Controller
{
    public function info(Request $request)
    {
        $connection = $request->attributes->get('delivery_connection');

        return response()->json(['data' => ['id' => $connection->id, 'name' => $connection->name, 'currency' => 'AED',
            'pickup_configured' => $connection->merchant->addresses()->whereKey($connection->delivery_pickup_address_id)->exists()]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, StoreDeliveryService $service)
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $key), 422, 'A 16–128 character Idempotency-Key is required.');
        $data = $request->validate([
            'external_order_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'currency' => ['required', 'in:AED'], 'payment_status' => ['required', 'in:paid'],
            'recipient' => ['required', 'array:contact_name,contact_phone,city,emirate,area,street,building,unit,address_line,landmark,makani_number,latitude,longitude'],
            'recipient.contact_name' => ['required', 'string', 'max:200'],
            'recipient.contact_phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'recipient.city' => ['required', 'string', 'max:120'],
            'recipient.emirate' => ['required', Rule::in(UaeAddressFields::EMIRATES)],
            'recipient.address_line' => ['required', 'string', 'min:5', 'max:1000'],
            'recipient.area' => ['nullable', 'string', 'max:120'], 'recipient.street' => ['nullable', 'string', 'max:200'],
            'recipient.building' => ['nullable', 'string', 'max:120'], 'recipient.unit' => ['nullable', 'string', 'max:80'],
            'recipient.landmark' => ['nullable', 'string', 'max:200'],
            'recipient.makani_number' => ['nullable', 'regex:/^[0-9]{10}$/', Rule::prohibitedIf($request->input('recipient.emirate') !== 'dubai')],
            'recipient.latitude' => ['nullable', 'required_with:recipient.longitude', 'numeric', 'between:22,27'],
            'recipient.longitude' => ['nullable', 'required_with:recipient.latitude', 'numeric', 'between:51,57'],
            'package_description' => ['nullable', 'string', 'max:200'], 'instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        // Typed canonical coordinates avoid false conflicts between 25 and 25.0 in JSON.
        foreach (['latitude', 'longitude'] as $field) {
            if (isset($data['recipient'][$field])) {
                $data['recipient'][$field] = (string) (float) $data['recipient'][$field];
            }
        }
        [$order, $created] = $service->create($request->attributes->get('delivery_connection'), $key, $data);

        return response()->json(['data' => $service->data($order), 'created' => $created], $created ? 201 : 200)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $order, StoreDeliveryService $service)
    {
        $owned = IntegrationDeliveryOrder::where('catalog_connection_id', $request->attributes->get('delivery_connection')->id)->findOrFail($order);

        return response()->json(['data' => $service->data($owned)])->header('Cache-Control', 'no-store');
    }
}
