<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        $merchant = Merchant::where(
            'user_id',
            $request->user()->id
        )->firstOrFail();

        $addresses = $merchant->addresses()
            ->orderBy('label')
            ->get();

        return response()->json([
            'data' => $addresses,
        ]);
    }
}
