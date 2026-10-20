<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\ProofOfDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCodController extends Controller
{
    private function ownsMerchant(Request $request, Delivery $delivery): bool
    {
        return $request->user()->hasRole('merchant') && Merchant::where('user_id', $request->user()->id)->whereKey($delivery->merchant_id)->exists();
    }

    private function driver(Request $request, Delivery $delivery, bool $acceptedOnly = false): Driver
    {
        abort_unless($request->user()->hasRole('driver'), 404);
        $driver = Driver::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($delivery->assignments()->where('driver_id', $driver->id)
            ->whereIn('status', $acceptedOnly ? ['accepted'] : ['pending', 'accepted'])->exists(), 404);
        return $driver;
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        if (! $this->ownsMerchant($request, $delivery)) $this->driver($request, $delivery);
        return response()->json(['data' => $delivery->codRecord()->first()]);
    }

    public function collect(Request $request, Delivery $delivery): JsonResponse
    {
        $driver = $this->driver($request, $delivery, true);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $record = DB::transaction(function () use ($request, $delivery, $driver) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            // Recheck ownership after acquiring the delivery lock.
            $this->driver($request, $locked, true);
            $record = $locked->codRecord()->lockForUpdate()->firstOrFail();
            if (in_array($record->status, ['collected', 'received'], true)) {
                abort_unless((int) $record->collected_by === (int) $request->user()->id, 403);
                return $record;
            }
            if ($record->status !== 'expected'
                || ! in_array($locked->status, [DeliveryStatus::Delivered, DeliveryStatus::Completed], true)
                || ! ProofOfDelivery::where('delivery_id', $locked->id)->where('driver_id', $driver->id)->whereNotNull('confirmed_at')->exists()) {
                throw ValidationException::withMessages(['cod' => 'Confirmed delivery proof is required before recording cash collection.']);
            }
            $record->update(['status' => 'collected', 'collected_by' => $request->user()->id, 'collected_at' => now()]);
            return $record->fresh();
        });
        return response()->json(['data' => $record]);
    }

    public function receive(Request $request, Delivery $delivery): JsonResponse
    {
        abort_unless($this->ownsMerchant($request, $delivery), 404);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $record = DB::transaction(function () use ($request, $delivery) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $record = $locked->codRecord()->lockForUpdate()->firstOrFail();
            if ($record->status === 'received') return $record;
            if ($record->status !== 'collected') {
                throw ValidationException::withMessages(['cod' => 'Driver collection must be recorded before merchant receipt.']);
            }
            $record->update(['status' => 'received', 'received_by' => $request->user()->id, 'received_at' => now()]);
            return $record->fresh();
        });
        return response()->json(['data' => $record]);
    }
}
