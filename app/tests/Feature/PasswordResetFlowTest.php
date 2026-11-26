<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Notifications\PasswordResetCodeNotification;
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

    public function test_user_can_verify_email_code_reset_password_and_start_login_otp(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['password' => Hash::make('old-password')]);
        $accessToken = $user->createToken('mobile')->accessToken;
        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'refresh-token-value'),
            'remember_me' => true,
            'expires_at' => now()->addDays(30),
        ]);

        $expectedMessage = 'If that email is registered, a password reset code has been sent.';
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', $expectedMessage);

        $code = null;
        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function (PasswordResetCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;
                $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

                $mail = $notification->toMail((object) ['name' => 'Pig World user']);
                $mailContent = implode("\n", $mail->introLines);
                $this->assertStringContainsString($code, $mailContent);
                $this->assertStringNotContainsString('http', $mailContent);
                $this->assertNull($mail->actionUrl);

                return true;
            },
        );
        $this->assertNotNull($code);

        $this->postJson('/api/v1/auth/verify-password-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('message', 'Code verified. You can now reset your password.');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $code,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath(
                'message',
                'Your password has been updated. You can now sign in to Pig World Smart.',
            );

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('otp_required', true);
        Notification::assertSentTo($user, LoginOtpNotification::class);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'old-password',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
        $this->assertNotNull($refreshToken->fresh()->revoked_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_unknown_email_gets_same_response_without_sending_a_code(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If that email is registered, a password reset code has been sent.',
            );

        Notification::assertNothingSent();
    }

    public function test_invalid_code_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->postJson('/api/v1/auth/verify-password-reset-code', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => '000000',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_expired_code_cannot_be_verified_or_reset_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;
        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function (PasswordResetCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );

        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/verify-password-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $code,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_code_verification_is_limited_after_five_invalid_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;
        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function (PasswordResetCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );
        $invalidCode = (string) (((((int) $code - 100000) + 1) % 900000) + 100000);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/verify-password-reset-code', [
                'email' => $user->email,
                'code' => $invalidCode,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('code');
        }

        $this->postJson('/api/v1/auth/verify-password-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
