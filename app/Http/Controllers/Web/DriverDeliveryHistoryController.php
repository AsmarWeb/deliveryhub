<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverDeliveryHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasRole('driver'), 403);
        $driver = Driver::where('user_id', $request->user()->id)->firstOrFail();
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,delivered,completed'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['q'] ?? '');
        // Only the last accepted driver gets credit; declined/expired offers are not deliveries.
        $base = Delivery::query()->whereIn('status', ['delivered', 'completed'])
            ->whereHas('assignments', function ($query) use ($driver) {
                $query->where('driver_id', $driver->id)->where('status', 'accepted')
                    ->where('id', function ($latest) {
                        $latest->selectRaw('MAX(latest_assignment.id)')->from('delivery_assignments as latest_assignment')
                            ->whereColumn('latest_assignment.delivery_id', 'delivery_assignments.delivery_id')
                            ->where('latest_assignment.status', 'accepted');
                    });
            });
        $counts = ['all' => (clone $base)->count(),
            'delivered' => (clone $base)->where('status', 'delivered')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count()];
        $deliveries = $base->with(['pickupAddress', 'dropoffAddress', 'assignments' => fn ($query) => $query
            ->where('driver_id', $driver->id)->where('status', 'accepted')->orderByDesc('id')])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereRaw("tracking_number LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
            ->orderByDesc('delivered_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('driver.history.index', compact('deliveries', 'counts', 'status', 'search'));
    }
}
