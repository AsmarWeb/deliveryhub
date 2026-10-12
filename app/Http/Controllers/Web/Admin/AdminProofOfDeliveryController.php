<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\ProofOfDeliveryAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminProofOfDeliveryController extends Controller
{
    public function file(
        Request $request,
        Delivery $delivery,
        string $type,
        ProofOfDeliveryAccessService $access
    ): BinaryFileResponse {
        $user = $request->user();

        abort_unless($user->hasRole('admin'), 403);

        $access->authorize($user, $delivery);

        return $access->file($delivery, $type);
    }
}
