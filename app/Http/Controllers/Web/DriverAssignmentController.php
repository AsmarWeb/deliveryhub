<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Services\Deliveries\DriverAssignmentService;
use Illuminate\Http\RedirectResponse;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverAssignmentController extends Controller
{
    private function driver(Request $request): Driver
    {
        abort_unless(
            $request->user()->hasRole('driver'),
            403
        );

        return Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): View
    {
        $driver = $this->driver($request);

        $status = $request->query('status', 'all');

        abort_unless(
            is_string($status) &&
                in_array($status, [
                    'all',
                    'pending',
                    'accepted',
                    'rejected',
                    'expired',
                ], true),
            404
        );

        $assignments = $driver->assignments()
            ->with([
                'delivery.pickupAddress',
                'delivery.dropoffAddress',
            ])
            ->when(
                $status !== 'all',
                fn($query) => $query->where('status', $status)
            )
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => $driver->assignments()->count(),
            'pending' => $driver->assignments()
                ->where('status', 'pending')->count(),
            'accepted' => $driver->assignments()
                ->where('status', 'accepted')->count(),
            'rejected' => $driver->assignments()
                ->where('status', 'rejected')->count(),
        ];

        return view('driver.assignments.index', compact(
            'driver',
            'assignments',
            'counts',
            'status'
        ));
    }

    public function show(
        Request $request,
        DeliveryAssignment $assignment
    ): View {
        $driver = $this->driver($request);

        abort_unless(
            $assignment->driver_id === $driver->id,
            404
        );

        $assignment->load([
            'delivery.pickupAddress',
            'delivery.dropoffAddress',
        ]);

        return view('driver.assignments.show', compact(
            'driver',
            'assignment'
        ));
    }


    public function accept(
        Request $request,
        DeliveryAssignment $assignment,
        DriverAssignmentService $service
    ): RedirectResponse {
        $driver = $this->driver($request);

        abort_unless($assignment->driver_id === $driver->id, 404);

        $service->accept(
            $assignment,
            $driver,
            $request->user()
        );

        return redirect()
            ->route('driver.assignments.show', $assignment)
            ->with(
                'success',
                app()->getLocale() === 'ar'
                    ? 'تم قبول المهمة بنجاح.'
                    : 'Assignment accepted successfully.'
            );
    }

    public function reject(
        Request $request,
        DeliveryAssignment $assignment,
        DriverAssignmentService $service
    ): RedirectResponse {
        $driver = $this->driver($request);

        abort_unless($assignment->driver_id === $driver->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->reject(
            $assignment,
            $driver,
            $request->user(),
            $data['reason']
        );

        return redirect()
            ->route('driver.assignments.show', $assignment)
            ->with(
                'success',
                app()->getLocale() === 'ar'
                    ? 'تم رفض المهمة بنجاح.'
                    : 'Assignment rejected successfully.'
            );
    }
}
