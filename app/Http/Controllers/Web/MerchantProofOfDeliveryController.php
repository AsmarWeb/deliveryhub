<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Services\Deliveries\ProofOfDeliveryAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MerchantProofOfDeliveryController extends Controller
{
    public function file(
        Request $request,
        Delivery $delivery,
        string $type,
        ProofOfDeliveryAccessService $access
    ): BinaryFileResponse {
        $user = $request->user();

        abort_unless($user->hasRole('merchant'), 403);

        $merchant = Merchant::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_unless(
            (int) $delivery->merchant_id === (int) $merchant->id,
            404
        );

        $access->authorize($user, $delivery);

        return $access->file($delivery, $type);
    }
}
