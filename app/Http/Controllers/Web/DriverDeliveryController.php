<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Services\Deliveries\DriverDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverDeliveryController extends Controller
{
    public function pickup(
        Request $request,
        DeliveryAssignment $assignment,
        DriverDeliveryService $service
    ): RedirectResponse {
        $delivery = $this->authorizedDelivery($request, $assignment);

        $service->pickup($delivery, $request->user());

        return redirect()
            ->route('driver.assignments.show', $assignment)
            ->with(
                'success',
                app()->getLocale() === 'ar'
                    ? 'تم تأكيد استلام الشحنة بنجاح.'
                    : 'Shipment pickup confirmed successfully.'
            );
    }

    public function startTransit(
        Request $request,
        DeliveryAssignment $assignment,
        DriverDeliveryService $service
    ): RedirectResponse {
        $delivery = $this->authorizedDelivery($request, $assignment);

        $service->startTransit($delivery, $request->user());

        return redirect()
            ->route('driver.assignments.show', $assignment)
            ->with(
                'success',
                app()->getLocale() === 'ar'
                    ? 'تم بدء التوصيل بنجاح.'
                    : 'Delivery transit started successfully.'
            );
    }

    private function authorizedDelivery(
        Request $request,
        DeliveryAssignment $assignment
    ): \App\Models\Delivery {
        abort_unless($request->user()->hasRole('driver'), 403);

        $driver = \App\Models\Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($assignment->driver_id === $driver->id, 404);

        abort_unless($assignment->status === 'accepted', 403);

        return $assignment->delivery()->firstOrFail();
    }
}
