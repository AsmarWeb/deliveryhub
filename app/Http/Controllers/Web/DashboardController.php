<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard
    ): View {
        $result = $dashboard->forUser($request->user());

        return view('dashboard.index', [
            'role' => $result['role'],
            'stats' => $result['data'],
        ]);
    }
}
