<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProofOfDeliveryController extends Controller
{

    public function store(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user->hasRole('driver'), 403);

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

        $storedPaths = [];

        try {
            $result = DB::transaction(function () use (
                $request,
                $delivery,
                $user,
                $validated,
                $transitions,
                &$storedPaths
            ) {
                $delivery = Delivery::query()
                    ->lockForUpdate()
                    ->findOrFail($delivery->id);

                $driver = Driver::query()
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $assignment = DeliveryAssignment::query()
                    ->where('delivery_id', $delivery->id)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'accepted')
                    ->first();

                abort_unless($assignment !== null, 403);

                if ($delivery->status !== DeliveryStatus::InTransit) {
                    throw ValidationException::withMessages([
                        'status' => 'Delivery must be in transit.',
                    ]);
                }

                if (ProofOfDelivery::where('delivery_id', $delivery->id)->exists()) {
                    throw ValidationException::withMessages([
                        'delivery' => 'Proof of delivery already exists.',
                    ]);
                }

                $photoPath = null;
                $signaturePath = null;

                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')->store(
                        "proof-of-deliveries/{$delivery->id}/photos",
                        'local'
                    );

                    if ($photoPath === false) {
                        throw new \RuntimeException('Photo upload failed.');
                    }

                    $storedPaths[] = $photoPath;
                }

                if ($request->hasFile('signature')) {
                    $signaturePath = $request->file('signature')->store(
                        "proof-of-deliveries/{$delivery->id}/signatures",
                        'local'
                    );

                    if ($signaturePath === false) {
                        throw new \RuntimeException('Signature upload failed.');
                    }

                    $storedPaths[] = $signaturePath;
                }

                $proof = ProofOfDelivery::create([
                    'delivery_id' => $delivery->id,
                    'driver_id' => $driver->id,
                    'recipient_name' => $validated['recipient_name'],
                    'notes' => $validated['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'signature_path' => $signaturePath,
                    'confirmed_at' => now(),
                ]);

                $updatedDelivery = $transitions->transition(
                    $delivery,
                    DeliveryStatus::Delivered,
                    $user,
                    'Delivery confirmed with proof of delivery'
                );

                return [
                    'proof' => $proof,
                    'delivery' => $updatedDelivery,
                ];
            });

            return response()->json([
                'message' => 'Proof of delivery recorded successfully.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
