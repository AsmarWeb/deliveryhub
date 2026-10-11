<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryPricingController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user->hasRole('admin'), 403);

        $data = $request->validate([
            'delivery_fee' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        $updated = DB::transaction(function () use (
            $delivery,
            $data,
            $user,
            $transitions
        ) {
            $locked = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            abort_unless(
                $locked->status === DeliveryStatus::PendingPricing,
                422,
                'Delivery is not awaiting pricing.'
            );

            $locked->delivery_fee = $data['delivery_fee'];
            $locked->save();

            return $transitions->transition(
                $locked,
                DeliveryStatus::Priced,
                $user,
                'Delivery price approved'
            );
        });

        return response()->json([
            'message' => 'Delivery priced successfully.',
            'data' => $updated,
        ]);
    }
}
