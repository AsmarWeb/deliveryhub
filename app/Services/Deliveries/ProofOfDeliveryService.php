<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Jobs\AutoCompleteDelivery;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProofOfDeliveryService
{
    public function __construct(
        private readonly DeliveryTransitionService $transitions
    ) {}

    public function store(
        Delivery $delivery,
        User $actor,
        array $data
    ): array {
        abort_unless($actor->hasRole('driver'), 403);

        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $delivery,
                $actor,
                $data,
                &$storedPaths
            ) {
                $delivery = Delivery::query()
                    ->lockForUpdate()
                    ->findOrFail($delivery->id);

                $driver = Driver::query()
                    ->where('user_id', $actor->id)
                    ->firstOrFail();

                $authorized = DeliveryAssignment::query()
                    ->where('delivery_id', $delivery->id)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'accepted')
                    ->exists();

                abort_unless($authorized, 403);

                if ($delivery->status !== DeliveryStatus::InTransit) {
                    throw ValidationException::withMessages([
                        'status' => 'Delivery must be in transit.',
                    ]);
                }

                if (ProofOfDelivery::query()
                    ->where('delivery_id', $delivery->id)
                    ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'delivery' => 'Proof of delivery already exists.',
                    ]);
                }

                $photoPath = $this->storeFile(
                    $data['photo'] ?? null,
                    "proof-of-deliveries/{$delivery->id}/photos",
                    $storedPaths
                );

                $signaturePath = $this->storeFile(
                    $data['signature'] ?? null,
                    "proof-of-deliveries/{$delivery->id}/signatures",
                    $storedPaths
                );

                $proof = ProofOfDelivery::create([
                    'delivery_id' => $delivery->id,
                    'driver_id' => $driver->id,
                    'recipient_name' => $data['recipient_name'],
                    'notes' => $data['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'signature_path' => $signaturePath,
                    'confirmed_at' => now(),
                ]);

                $updated = $this->transitions->transition(
                    $delivery,
                    DeliveryStatus::Delivered,
                    $actor,
                    'Delivery confirmed with proof of delivery'
                );

                AutoCompleteDelivery::dispatch($delivery->id)
                    ->afterCommit();

                return [
                    'proof' => $proof,
                    'delivery' => $updated,
                ];
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    private function storeFile(
        ?UploadedFile $file,
        string $directory,
        array &$storedPaths
    ): ?string {
        if ($file === null) {
            return null;
        }

        $path = $file->store($directory, 'local');

        if ($path === false) {
            throw new \RuntimeException('File upload failed.');
        }

        $storedPaths[] = $path;

        return $path;
    }
}
