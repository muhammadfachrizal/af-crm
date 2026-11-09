<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\DeviceToken;

/** Mobile mendaftarkan token FCM miliknya setelah login. */
class DeviceTokenController extends ApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        // Token yang sama bisa berpindah user/tenant (ganti akun di HP yang sama).
        DeviceToken::withoutGlobalScopes()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'tenant_id' => \VentureDrake\LaravelCrm\Support\CurrentTenant::id() ?? $request->user()->tenant_id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request)
    {
        $token = $request->input('token');

        if ($token) {
            DeviceToken::where('token', $token)->where('user_id', $request->user()->id)->delete();
        }

        return response()->noContent();
    }
}
