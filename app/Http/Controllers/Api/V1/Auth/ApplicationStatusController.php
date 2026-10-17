<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $merchant = Merchant::query()
            ->where('user_id', $user->id)
            ->first();

        $driver = Driver::query()
            ->where('user_id', $user->id)
            ->first();

        if ($merchant && $driver) {
            return response()->json([
                'message' => 'Multiple application profiles found.',
                'code' => 'APPLICATION_CONFLICT',
            ], 409);
        }

        if ($merchant) {
            return $this->respond(
                'merchant',
                $merchant->status,
                $merchant->business_name
            );
        }

        if ($driver) {
            return $this->respond(
                'driver',
                $driver->status,
                null
            );
        }

        return response()->json([
            'message' => 'Application not found.',
            'code' => 'APPLICATION_NOT_FOUND',
        ], 404);
    }

    private function respond(
        string $type,
        string $status,
        ?string $businessName
    ): JsonResponse {
        return response()->json([
            'application' => [
                'type' => $type,
                'status' => $status,
                'business_name' => $businessName,
                'can_operate' => $status === 'active',
            ],
        ]);
    }
}
