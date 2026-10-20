<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantFinanceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', Rule::in(array_column(DeliveryStatus::cases(), 'value'))],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ]);
        $records = Delivery::query()->where('merchant_id', $merchant->id)
            ->whereNotNull('delivery_fee')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency', $currency))
            ->with('codRecord')->with(['commission' => fn ($query) => $query->where('merchant_id', $merchant->id)])
            ->orderByDesc('id')->paginate(20);
        return response()->json([
            'data' => $records->getCollection()->map(fn ($delivery) => [
                'id' => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'status' => $delivery->status->value,
                'delivery_fee' => $delivery->delivery_fee,
                'currency' => $delivery->currency,
                'created_at' => $delivery->created_at?->toIso8601String(),
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'cod_record' => $delivery->codRecord?->only(['expected_amount', 'currency', 'status', 'collected_at', 'received_at']),
                'commission' => $delivery->commission ? $delivery->commission->only([
                    'gross_amount', 'commission_rate', 'platform_amount', 'partner_amount', 'currency', 'status',
                ]) : null,
            ]),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }
}
