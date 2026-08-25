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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly TokenIssuer $tokens)
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
            : Farm::where('invite_code', $request->string('invite_code')->toString())->firstOrFail();

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'role' => $role,
        ]);

        $user->farms()->attach($farm->id);

        return $this->authResponse($user);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            // Same message whether the email exists or not, so we don't leak account existence.
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
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
        $request->user()?->currentAccessToken()?->delete();

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
