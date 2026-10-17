<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDriverController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('admin'),
            403
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['pending', 'active', 'inactive']),
            ],
        ]);

        $drivers = Driver::query()
            ->with('user:id,name,email')
            ->withCount([
                'assignments',
                'assignments as active_assignments_count' =>
                fn($query) => $query
                    ->whereIn('status', ['pending', 'accepted'])
                    ->whereHas(
                        'delivery',
                        fn($delivery) =>
                        $delivery->whereIn('status', [
                            'assigned',
                            'accepted',
                            'pickup',
                            'in_transit',
                        ])
                    ),
            ])
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('phone', 'like', "%{$search}%")
                            ->orWhereHas(
                                'user',
                                fn($user) => $user
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                            );
                    });
                }
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, $status) =>
                $query->where('status', $status)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.drivers.index', [
            'drivers' => $drivers,
        ]);
    }

    public function updateStatus(
        Request $request,
        Driver $driver
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        DB::transaction(function () use ($driver, $data) {
            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            if ($data['status'] === 'inactive') {
                $hasActiveAssignments = $driver
                    ->assignments()
                    ->whereIn('status', ['pending', 'accepted'])
                    ->exists();

                if ($hasActiveAssignments) {
                    throw ValidationException::withMessages([
                        'status' => 'Driver has active assignments.',
                    ]);
                }
            }

            $driver->update([
                'status' => $data['status'],
                'is_available' => false,
            ]);

            if ($data['status'] === 'active') {
                $user = $driver->user;

                if (! $user->hasRole('driver')) {
                    $user->addRole('driver');
                }
            }
        });

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', 'Driver status updated successfully.');
    }

    public function location(
        Request $request,
        Driver $driver
    ): JsonResponse {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => [
                'driver_id' => $driver->id,
                'driver_name' => $driver->user?->name,
                'status' => $driver->status,
                'is_available' => $driver->is_available,
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'last_location_at' =>
                $driver->last_location_at?->toISOString(),
                'has_location' =>
                $driver->current_latitude !== null
                    && $driver->current_longitude !== null,
            ],
        ]);
    }


    public function map(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.drivers.map');
    }

    public function locations(
        Request $request
    ): \Illuminate\Http\JsonResponse {
        $this->authorizeAdmin($request);

        $drivers = Driver::query()
            ->with('user:id,name')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereNotNull('last_location_at')
            ->where('last_location_at', '>=', now()->subHours(24))
            ->get();

        return response()->json([
            'data' => $drivers->map(function (Driver $driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->user?->name,
                    'latitude' => (float) $driver->current_latitude,
                    'longitude' => (float) $driver->current_longitude,
                    'last_location_at' =>
                    $driver->last_location_at?->toISOString(),
                    'is_recent' =>
                    $driver->last_location_at?->gte(
                        now()->subMinutes(5)
                    ) ?? false,
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
