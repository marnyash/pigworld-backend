<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\FarmResource;
use App\Http\Resources\UserResource;
use App\Models\Farm;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\Auth\FirebaseIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenIssuer $tokens,
        private readonly FirebaseIdTokenVerifier $firebaseTokens,
    )
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $role = $request->string('role')->toString();

        $farm = $role === 'farmOwner'
            ? Farm::create($request->safe()->only([
                'mother_pig_count',
                'piglet_groups',
                'pregnant_pig_count',
            ]) + ['name' => $request->string('farm_name')->toString()])
            : ($request->filled('invite_code')
                ? Farm::where('invite_code', $request->string('invite_code')->toString())->firstOrFail()
                : null);

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->string('phone'),
            'password' => Hash::make($request->string('password')),
            'role' => $role,
        ]);

        if ($farm !== null) {
            $user->farms()->attach($farm->id);
        }

        return $this->authResponse($user);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->string('identifier')->toString();
        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if ($user === null || $user->crm_closed_at !== null || ! Hash::check($request->string('password'), $user->password)) {
            // Same message whether the identifier exists or not, so we don't leak account existence.
            throw ValidationException::withMessages(['identifier' => ['These credentials do not match our records.']]);
        }

        return $this->authResponse($user);
    }

    public function loginWithGoogle(Request $request): JsonResponse
    {
        $idToken = $request->validate(['id_token' => ['required', 'string']])['id_token'];
        $claims = $this->firebaseTokens->verify($idToken);
        $email = $claims['email'] ?? null;

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ($claims['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['id_token' => ['The Google account email is not verified.']]);
        }

        $user = User::where('email', $email)->first();
        if ($user === null || $user->crm_closed_at !== null) {
            throw ValidationException::withMessages(['email' => ['No active Pig World account is associated with this Google email.']]);
        }

        return $this->authResponse($user);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $stored = $this->tokens->findValid($request->string('refresh_token')->toString());

        if ($stored === null) {
            throw ValidationException::withMessages(['refresh_token' => ['This refresh token is invalid or has expired.']]);
        }

        $this->tokens->revoke($stored);

        return $this->authResponse($stored->user()->firstOrFail());
    }

    public function logout(Request $request): JsonResponse
    {
        if ($token = $request->bearerToken()) {
            PersonalAccessToken::findToken($token)?->delete();
        }

        $request->user()->refreshTokens()->update(['revoked_at' => now()]);

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'farms' => FarmResource::collection($user->farms),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $request->user()->update($data);

        return response()->json(['user' => new UserResource($request->user()->fresh())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Always respond the same way to avoid revealing whether an account exists for this email.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    private function authResponse(User $user): JsonResponse
    {
        $pair = $this->tokens->issue($user);

        return response()->json([
            'access_token' => $pair['access_token'],
            'refresh_token' => $pair['refresh_token'],
            'user' => new UserResource($user),
            'farms' => FarmResource::collection($user->farms),
        ]);
    }
}
