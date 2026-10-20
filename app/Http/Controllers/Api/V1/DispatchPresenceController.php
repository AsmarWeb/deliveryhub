<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchPresenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('driver'), 403);
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['required_with:latitude', 'nullable', 'numeric', 'between:0,500'],
            'recorded_at' => ['required_with:latitude', 'nullable', 'date',
                'after_or_equal:'.now()->subMinutes(2)->toIso8601String(), 'before_or_equal:'.now()->addSeconds(30)->toIso8601String()],
        ]);
        DB::transaction(function () use ($request, $data) {
            $driver = Driver::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('enabled', $data) && ! $data['enabled']) {
                $driver->update(['dispatch_enabled' => false, 'dispatch_seen_at' => null, 'dispatch_location_at' => null,
                    'dispatch_latitude' => null, 'dispatch_longitude' => null]);
                return;
            }
            abort_unless($driver->dispatch_enabled || ($data['enabled'] ?? false), 422, 'Enable automatic dispatch first.');
            abort_unless($driver->status === 'active' && $driver->is_available, 422, 'Become available before updating dispatch presence.');
            if (($data['enabled'] ?? false) && ! isset($data['latitude'], $data['longitude'])) {
                abort(422, 'A current location is required to enable dispatch.');
            }
            $updates = ['dispatch_seen_at' => now()];
            if ($data['enabled'] ?? false) $updates['dispatch_enabled'] = true;
            if (isset($data['latitude'], $data['longitude'])) {
                $fixTime = Carbon::parse($data['recorded_at'])->setTimezone(config('app.timezone', 'UTC'));
                if (! $driver->dispatch_location_at || $fixTime->gte($driver->dispatch_location_at)) {
                    $updates += ['dispatch_latitude' => $data['latitude'], 'dispatch_longitude' => $data['longitude'], 'dispatch_location_at' => $fixTime];
                }
            }
            $driver->update($updates);
        });
        return response()->json(['message' => 'Dispatch presence updated.']);
    }
}
