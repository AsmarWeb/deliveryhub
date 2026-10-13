<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard
    ): View {
        abort_unless($request->user()->hasRole('admin'), 403);

        $result = $dashboard->forUser($request->user());

        $statusCounts = Delivery::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentDeliveries = Delivery::query()
            ->with('merchant:id,business_name')
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.admin', [
            'stats' => $result['data'],
            'statusCounts' => $statusCounts,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }
}
