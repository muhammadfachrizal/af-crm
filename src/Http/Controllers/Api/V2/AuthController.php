<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\IssueTokenRequest;
use Illuminate\Support\Facades\Password;

class AuthController extends ApiController
{
    public function issueToken(IssueTokenRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $userModel = config('auth.providers.users.model');

        $user = $userModel::where('email', $credentials['email'])->first();

        if (! $user
            || ! Hash::check($credentials['password'], $user->password)
            || ! (bool) ($user->crm_access ?? false)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! empty($user->tenant_id)) {
            $tenant = \VentureDrake\LaravelCrm\Models\Tenant::find($user->tenant_id);

            if (! $tenant || ! $tenant->isUsable()) {
                throw ValidationException::withMessages([
                    'email' => ['Akun perusahaan Anda nonaktif atau masa langganan telah berakhir. Hubungi administrator.'],
                ]);
            }
        }

        $deviceName = $credentials['device_name'] ?? ($request->userAgent() ?: 'api-token');

        $token = $user->createToken($deviceName);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->transformUser($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->transformUser($request->user()),
        ]);
    }

    public function revokeToken(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->noContent();
    }

    protected function transformUser($user): array
    {
        $user->loadMissing([
            'roles',
            'crmTeams',
            'phones',
        ]);

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'phone' => optional($user->phones->firstWhere('primary', 1))->number,
            'role' => optional($user->roles->first())->name,
            'tenant' => $this->transformTenant($user),
            'teams' => $user->crmTeams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                ];
            })->values(),
        ];
    }

    protected function transformTenant($user): ?array
    {
        if (empty($user->tenant_id)) {
            return null; // super admin
        }

        $t = \VentureDrake\LaravelCrm\Models\Tenant::find($user->tenant_id);

        return $t ? [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'logo_url' => $t->logo_url,
            'attendance_enforce_radius' => (bool) $t->attendance_enforce_radius,
            'hris_app_url' => $t->hris_app_url,
        ] : null;
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password saat ini salah.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $request->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Link reset password telah dikirim ke email.',
            ]);
        }

        return response()->json([
            'message' => trans($status),
        ], 422);
    }
}
