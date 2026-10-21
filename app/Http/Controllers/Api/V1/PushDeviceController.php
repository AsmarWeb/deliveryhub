<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
class PushDeviceController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(config('push.enabled') && filled(config('push.project_id')), 503, 'Push notifications are not configured.');
        $session = $request->user()->currentAccessToken();
        abort_unless($session instanceof PersonalAccessToken, 403);
        abort_unless($request->user()->hasRole('merchant') || $request->user()->hasRole('driver'), 403);
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'token' => ['required', 'string', 'max:255', 'regex:/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/D'],
            'project_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:ios,android'],
            'language' => ['required', 'in:ar,en'],
        ]);
        abort_unless((int) $data['session_id'] === (int) $session->id, 409, 'Session changed.');
        abort_unless(hash_equals((string) config('push.project_id'), $data['project_id']), 422, 'Incorrect push project.');
        DB::table('push_devices')->upsert([[
            'token_hash' => hash('sha256', $data['token']),
            'user_id' => $request->user()->id, 'access_token_id' => $session->id,
            'token' => encrypt($data['token']), 'platform' => $data['platform'], 'language' => $data['language'],
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]], ['token_hash'], ['user_id', 'access_token_id', 'token', 'platform', 'language', 'expires_at', 'updated_at']);
        return response()->json(['message' => 'Push device registered.']);
    }
    public function destroy(Request $request)
    {
        $session = $request->user()->currentAccessToken();
        abort_unless($session instanceof PersonalAccessToken, 403);
        $request->validate(['session_id' => ['required', 'integer']]);
        abort_unless((int) $request->input('session_id') === (int) $session->id, 409, 'Session changed.');
        DB::table('push_devices')->where('user_id', $request->user()->id)->where('access_token_id', $session->id)->delete();
        return response()->json(['message' => 'Push devices removed for this session.']);
    }
}
