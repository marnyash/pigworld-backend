<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_with_a_phone_and_login_by_phone(): void
    {
        $registration = $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '+15551234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'farmOwner',
            'farm_name' => 'Green Valley Farm',
        ]);

        $registration->assertOk()
            ->assertJsonPath('user.phone', '+15551234567')
            ->assertJsonPath('user.role', 'farmOwner')
            ->assertJsonPath('farms.0.name', 'Green Valley Farm');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => '+15551234567',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure([
            'access_token',
            'refresh_token',
            'user' => ['id', 'phone', 'role'],
            'farms',
        ]);
    }

    public function test_login_rejects_invalid_credentials_and_closed_crm_accounts(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'phone' => '+15557654321',
            'password' => bcrypt('password123'),
            'crm_closed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('identifier');

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '+15557654321',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('identifier');
    }

    public function test_authenticated_user_can_read_me_and_logout_revokes_access_token(): void
    {
        $user = User::factory()->create([
            'role' => 'farmOwner',
            'phone' => '+15550001111',
        ]);
        $farm = Farm::create(['name' => 'Test Farm']);
        $user->farms()->attach($farm);
        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('access_token');
        $refreshToken = $login->json('refresh_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('farms.0.name', 'Test Farm');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnprocessable()->assertJsonValidationErrors('refresh_token');
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_user_can_lookup_legacy_farm_members_collection(): void
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $member = User::factory()->create(['role' => 'farmMember']);
        $farm = Farm::create(['name' => 'Legacy Farm']);

        $owner->farms()->attach($farm);
        $farm->users()->attach($member);

        $members = $owner->farmMembers();

        $this->assertTrue($members->contains(fn (User $user) => $user->id === $owner->id));
        $this->assertTrue($members->contains(fn (User $user) => $user->id === $member->id));
    }

    public function test_seeded_tester_can_login_outside_production(): void
    {
        $this->seed();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_production_seed_removes_the_predictable_tester_account(): void
    {
        User::factory()->create(['email' => 'test@example.com']);
        config(['app.env' => 'production']);

        $this->seed();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
