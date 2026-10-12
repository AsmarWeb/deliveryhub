<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\DeliveryCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminDeliveryCompletionController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryCompletionService $completion
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('admin'), 403);

        $completion->complete($delivery, $request->user());

        return redirect()
            ->route('admin.deliveries.show', $delivery)
            ->with('success', 'Delivery completed successfully.');
    }
}
