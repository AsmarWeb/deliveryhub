<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\Dispatch\DriverMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AvailableDriversController extends Controller
{
    public function __invoke(Request $request, DriverMatcher $matcher): JsonResponse
    {
        abort_unless($request->user()->hasRole('merchant'), 403);
        return response()->json(['data' => $matcher->available()->take(10)->map(fn ($driver) => [
            'id' => $driver->id,
            'display_name' => preg_split('/\s+/u', trim($driver->user->name))[0] ?: 'Driver #'.$driver->id,
            'vehicle_type' => $driver->vehicle_type,
        ])->values()]);
    }
}
