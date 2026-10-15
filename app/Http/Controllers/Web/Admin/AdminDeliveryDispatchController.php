<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\Deliveries\DeliveryAssignmentService;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDeliveryDispatchController extends Controller
{
    public function price(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'delivery_fee' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        DB::transaction(function () use (
            $delivery,
            $data,
            $request,
            $transitions
        ) {
            $locked = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($locked->status !== DeliveryStatus::PendingPricing) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'delivery_fee' => 'Delivery is not awaiting pricing.',
                ]);
            }

            $locked->delivery_fee = $data['delivery_fee'];
            $locked->save();

            $transitions->transition(
                $locked,
                DeliveryStatus::Priced,
                $request->user(),
                'Delivery price approved by admin'
            );
        });

        return back()->with(
            'success',
            'Delivery priced successfully.'
        );
    }

    public function assign(
        Request $request,
        Delivery $delivery,
        DeliveryAssignmentService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
            ],
        ]);

        $driver = Driver::findOrFail($data['driver_id']);

        $service->assign(
            $delivery,
            $driver,
            $request->user()
        );

        return back()->with(
            'success',
            'Delivery assigned successfully.'
        );
    }
}
