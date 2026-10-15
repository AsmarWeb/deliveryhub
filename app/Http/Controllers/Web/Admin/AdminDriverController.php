<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $drivers = Driver::query()
            ->with('user:id,name,email')
            ->withCount([
                'assignments',
                'assignments as active_assignments_count' =>
                fn($query) => $query->whereIn(
                    'status',
                    ['pending', 'accepted']
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
        });

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', 'Driver status updated successfully.');
    }
}
