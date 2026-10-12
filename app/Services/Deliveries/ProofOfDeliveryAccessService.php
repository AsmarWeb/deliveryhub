<?php

namespace App\Services\Deliveries;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProofOfDeliveryAccessService
{
    public function authorize(User $user, Delivery $delivery): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('merchant')) {
            $merchant = Merchant::query()
                ->where('user_id', $user->id)
                ->first();

            abort_unless(
                $merchant !== null
                && (int) $delivery->merchant_id === (int) $merchant->id,
                403
            );

            return;
        }

        if ($user->hasRole('driver')) {
            $driver = Driver::query()
                ->where('user_id', $user->id)
                ->first();

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

    public function proof(Delivery $delivery): ProofOfDelivery
    {
        return ProofOfDelivery::query()
            ->where('delivery_id', $delivery->id)
            ->firstOrFail();
    }

    public function file(
        Delivery $delivery,
        string $type
    ): BinaryFileResponse {
        abort_unless(
            in_array($type, ['photo', 'signature'], true),
            404
        );

        $proof = $this->proof($delivery);

        $path = $type === 'photo'
            ? $proof->photo_path
            : $proof->signature_path;

        abort_unless(is_string($path) && $path !== '', 404);

        $directory = $type === 'photo'
            ? 'photos'
            : 'signatures';

        $prefix = "proof-of-deliveries/{$delivery->id}/{$directory}/";

        abort_unless(
            str_starts_with($path, $prefix)
            && !str_contains($path, '..')
            && !str_contains($path, '\\'),
            404
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $disk->mimeType($path)
                ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
