<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_page_is_not_exposed_on_the_web(): void
    {
        $this->get('/reset-password')->assertNotFound();
        $this->get('/reset-password/reset-token')->assertNotFound();
    }

    public function test_user_can_request_email_and_complete_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $accessToken = $user->createToken('mobile')->accessToken;
        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'refresh-token-value'),
            'remember_me' => true,
            'expires_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a reset link has been sent.');

        $plainToken = null;
        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($user, &$plainToken): bool {
            $plainToken = $notification->token;
            $mail = $notification->toMail($user);
            $this->assertSame('emails.auth.password-reset', $mail->view);
            $this->assertStringStartsWith(
                'pigworldsmart://open/reset-password?',
                $mail->viewData['resetUrl'],
            );
            $this->assertStringNotContainsString(
                'api.pigworldsmart.com',
                $mail->viewData['resetUrl'],
            );
            $this->assertStringContainsString($plainToken, $mail->viewData['resetUrl']);
            $this->assertStringContainsString(rawurlencode($user->email), $mail->viewData['resetUrl']);
            $renderedMail = view($mail->view, $mail->viewData)->render();
            $this->assertStringContainsString(
                'href="'.htmlspecialchars($mail->viewData['resetUrl'], ENT_QUOTES, 'UTF-8').'"',
                $renderedMail,
            );

            return true;
        });
        $this->assertNotNull($plainToken);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath(
                'message',
                'Your password has been updated. You can now sign in to Pig World Smart.',
            );

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('otp_required', true);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'old-password',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
        $this->assertNotNull($refreshToken->fresh()->revoked_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_invalid_password_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
