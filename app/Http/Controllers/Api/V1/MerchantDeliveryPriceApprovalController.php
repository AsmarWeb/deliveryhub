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

class MerchantDeliveryPriceApprovalController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user->hasRole('merchant'), 403);

        $merchant = Merchant::where('user_id', $user->id)
            ->firstOrFail();

        abort_unless(
            $delivery->merchant_id === $merchant->id,
            403
        );

        $updated = DB::transaction(function () use (
            $delivery,
            $user,
            $transitions
        ) {
            $locked = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if (
                $locked->status !== DeliveryStatus::Priced ||
                $locked->delivery_fee === null
            ) {
                throw ValidationException::withMessages([
                    'delivery' => [
                        'Delivery is not awaiting price approval.',
                    ],
                ]);
            }

            return $transitions->transition(
                $locked,
                DeliveryStatus::Matching,
                $user,
                'Merchant approved delivery price',
                [
                    'approved_fee' => (string) $locked->delivery_fee,
                    'currency' => $locked->currency,
                ]
            );
        });

        return response()->json([
            'message' => 'Delivery price approved successfully.',
            'data' => $updated,
        ]);
    }
}
