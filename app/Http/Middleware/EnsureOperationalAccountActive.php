<?php

namespace App\Http\Middleware;

use App\Models\Driver;
use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalAccountActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Administrators retain access to operational APIs.
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Check onboarding records before checking operational roles.
        // New driver applicants intentionally have no driver role.
        $merchant = Merchant::where('user_id', $user->id)->first();
        $driver = Driver::where('user_id', $user->id)->first();

        if ($merchant && $merchant->status !== 'active') {
            return $this->deny();
        }

        if ($driver && $driver->status !== 'active') {
            return $this->deny();
        }

        if ($user->hasRole('merchant') && ! $merchant) {
            return response()->json([
                'message' => 'Merchant profile not found.',
            ], 404);
        }

        if ($user->hasRole('driver') && ! $driver) {
            return response()->json([
                'message' => 'Driver profile not found.',
            ], 404);
        }

        // An applicant without an operational role is also blocked.
        if (
            ! $user->hasRole('merchant')
            && ! $user->hasRole('driver')
        ) {
            return $this->deny();
        }

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json([
            'message' => 'Your account is awaiting approval or is inactive.',
            'code' => 'ACCOUNT_NOT_ACTIVE',
        ], 403);
    }
}
