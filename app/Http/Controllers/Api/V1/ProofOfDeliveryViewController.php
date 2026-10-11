<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProofOfDeliveryViewController extends Controller
{
    private function authorizeView(Request $request, Delivery $delivery): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('merchant')) {
            $merchant = \App\Models\Merchant::query()
                ->where('user_id', $user->id)
                ->first();

            abort_unless(
                $merchant !== null &&
                (int) $delivery->merchant_id === (int) $merchant->id,
                403
            );

            return;
        }

        if ($user->hasRole('driver')) {
            $driver = Driver::where('user_id', $user->id)->first();

            abort_unless($driver !== null, 403);

            $assigned = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('driver_id', $driver->id)
                ->where('status', 'accepted')
                ->exists();

            abort_unless($assigned, 403);

            return;
        }

        abort(403);
    }

    public function show(
        Request $request,
        Delivery $delivery
    ): JsonResponse {
        $this->authorizeView($request, $delivery);

        $proof = ProofOfDelivery::query()
            ->where('delivery_id', $delivery->id)
            ->firstOrFail();

        return response()->json([
            'message' => 'Proof of delivery retrieved successfully.',
            'data' => [
                'id' => $proof->id,
                'delivery_id' => $proof->delivery_id,
                'driver_id' => $proof->driver_id,
                'recipient_name' => $proof->recipient_name,
                'notes' => $proof->notes,
                'confirmed_at' => $proof->confirmed_at,
                'has_photo' => $proof->photo_path !== null,
                'has_signature' => $proof->signature_path !== null,
            ],
        ]);
    }

    public function file(
        Request $request,
        Delivery $delivery,
        string $type
    ): BinaryFileResponse {
        $this->authorizeView($request, $delivery);

        abort_unless(
            in_array($type, ['photo', 'signature'], true),
            404
        );

        $proof = ProofOfDelivery::query()
            ->where('delivery_id', $delivery->id)
            ->firstOrFail();

        $path = $type === 'photo'
            ? $proof->photo_path
            : $proof->signature_path;

        abort_unless(
            $path && Storage::disk('local')->exists($path),
            404
        );

        return response()->file(
            Storage::disk('local')->path($path),
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
