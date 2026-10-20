<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Services\Deliveries\DeliveryTransitionService;
use App\Services\Pricing\AutomaticDeliveryPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryTransitionController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $service,
        AutomaticDeliveryPricingService $autoPricing
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->hasRole('admin') || $user->hasRole('merchant'),
            403
        );

        if (! $user->hasRole('admin')) {
            $merchant = Merchant::where('user_id', $user->id)
                ->firstOrFail();

            abort_unless(
                $delivery->merchant_id === $merchant->id,
                403
            );
        }

        $data = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::enum(DeliveryStatus::class),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $to = DeliveryStatus::from($data['status']);

        abort_unless(
            $delivery->status === DeliveryStatus::Draft
                && $to === DeliveryStatus::PendingPricing,
            403,
            'This transition is not available through this endpoint.'
        );

        $updated = DB::transaction(function () use (
            $delivery,
            $to,
            $user,
            $data,
            $service,
            $autoPricing
        ) {
            $submitted = $service->transition(
                $delivery,
                $to,
                $user,
                $data['reason'] ?? null
            );

            $priced = $autoPricing->price($submitted, $user);

            return $priced ?? $submitted->refresh();
        });

        return response()->json([
            'message' => 'Delivery status updated successfully.',
            'data' => $updated,
        ]);
    }
}
