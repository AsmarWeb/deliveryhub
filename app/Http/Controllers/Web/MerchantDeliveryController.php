<?php

namespace App\Http\Controllers\Web;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->hasRole('merchant'),
            403
        );

        $merchant = Merchant::where(
            'user_id',
            $request->user()->id
        )->firstOrFail();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:' . implode(
                ',',
                array_column(DeliveryStatus::cases(), 'value')
            )],
        ]);

        $deliveries = Delivery::query()
            ->where('merchant_id', $merchant->id)
            ->when(
                $validated['search'] ?? null,
                fn($query, $search) => $query->where(
                    'tracking_number',
                    'like',
                    '%' . $search . '%'
                )
            )
            ->when(
                $validated['status'] ?? null,
                fn($query, $status) => $query->where(
                    'status',
                    $status
                )
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('merchant.deliveries.index', [
            'deliveries' => $deliveries,
            'statuses' => DeliveryStatus::cases(),
        ]);
    }


    public function show(Request $request, Delivery $delivery): View
    {
        abort_unless(
            $request->user()->hasRole('merchant'),
            403
        );

        $merchant = Merchant::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Prevent merchants from viewing another merchant's delivery.
        abort_unless(
            (int) $delivery->merchant_id === (int) $merchant->id,
            404
        );

        $delivery->load([
            'pickupAddress',
            'dropoffAddress',
            'statusHistories' => fn($query) => $query
                ->with('actor')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return view('merchant.deliveries.show', [
            'delivery' => $delivery,
        ]);
    }
}
