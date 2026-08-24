<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TokenIssuer
{
    private const ACCESS_TOKEN_TTL_MINUTES = 15;
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    /** @return array{access_token: string, refresh_token: string} */
    public function issue(User $user): array
    {
        $accessToken = $user->createToken(
            'mobile',
            ['*'],
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
        )->plainTextToken;

        $refreshToken = Str::random(64);
        $user->refreshTokens()->create([
            'token_hash' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return ['access_token' => $accessToken, 'refresh_token' => $refreshToken];
    }

    public function findValid(string $rawRefreshToken): ?RefreshToken
    {
        $token = RefreshToken::query()
            ->where('token_hash', hash('sha256', $rawRefreshToken))
            ->first();

        return $token !== null && $token->isValid() ? $token : null;
    }

    public function revoke(RefreshToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }
}
