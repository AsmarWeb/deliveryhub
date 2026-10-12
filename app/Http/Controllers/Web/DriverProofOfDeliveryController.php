<?php

namespace App\Http\Controllers\Web;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Services\Deliveries\ProofOfDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverProofOfDeliveryController extends Controller
{
    public function create(
        Request $request,
        DeliveryAssignment $assignment
    ): View {
        $delivery = $this->authorizedDelivery(
            $request,
            $assignment
        );

        abort_unless(
            $delivery->status === DeliveryStatus::InTransit,
            404
        );

        return view('driver.assignments.proof', compact(
            'assignment',
            'delivery'
        ));
    }

    public function store(
        Request $request,
        DeliveryAssignment $assignment,
        ProofOfDeliveryService $service
    ): RedirectResponse {
        $delivery = $this->authorizedDelivery(
            $request,
            $assignment
        );

        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'signature' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $service->store(
            $delivery,
            $request->user(),
            $validated
        );

        return redirect()
            ->route('driver.assignments.show', $assignment)
            ->with(
                'success',
                app()->getLocale() === 'ar'
                    ? 'تم تسجيل إثبات التسليم بنجاح.'
                    : 'Proof of delivery recorded successfully.'
            );
    }

    private function authorizedDelivery(
        Request $request,
        DeliveryAssignment $assignment
    ): \App\Models\Delivery {
        abort_unless($request->user()->hasRole('driver'), 403);

        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless(
            $assignment->driver_id === $driver->id,
            404
        );

        abort_unless(
            $assignment->status === 'accepted',
            403
        );

        return $assignment->delivery()->firstOrFail();
    }
}
