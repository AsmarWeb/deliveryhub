<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantDeliveryCancellationController extends Controller
{
    public function __invoke(Request $request, Delivery $delivery, DeliveryTransitionService $transitions): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless((int) $delivery->merchant_id === (int) $merchant->id, 404);
        if (is_string($request->input('reason'))) $request->merge(['reason' => trim($request->input('reason'))]);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $updated = DB::transaction(function () use ($delivery, $merchant, $request, $data, $transitions) {
            $locked = Delivery::where('merchant_id', $merchant->id)->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            // Retries must not add another history or change the original reason.
            if ($locked->status === DeliveryStatus::Cancelled) return $locked;
            if (! in_array($locked->status, [DeliveryStatus::Draft, DeliveryStatus::PendingPricing,
                DeliveryStatus::Priced, DeliveryStatus::Matching, DeliveryStatus::Expired], true)
                || $locked->picked_up_at !== null || $locked->delivered_at !== null
                || $locked->assignments()->whereIn('status', ['pending', 'accepted'])->exists()) {
                throw ValidationException::withMessages([
                    'delivery' => 'This delivery cannot be cancelled online. Refresh the order or contact support.',
                ]);
            }
            return $transitions->transition($locked, DeliveryStatus::Cancelled, $request->user(),
                $data['reason'], ['cancellation_source' => 'merchant']);
        });
        return response()->json(['message' => 'Delivery cancelled.', 'data' => $updated->load(['pickupAddress', 'dropoffAddress'])]);
    }
}
