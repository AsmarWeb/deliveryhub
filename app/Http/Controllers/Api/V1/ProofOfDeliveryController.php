<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\ProofOfDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofOfDeliveryController extends Controller
{
    public function store(
        Request $request,
        Delivery $delivery,
        ProofOfDeliveryService $service
    ): JsonResponse {
        abort_unless($request->user()->hasRole('driver'), 403);

        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'signature' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $result = $service->store(
            $delivery,
            $request->user(),
            $validated
        );

        return response()->json([
            'message' => 'Proof of delivery recorded successfully.',
            'data' => $result,
        ], 201);
    }
}
