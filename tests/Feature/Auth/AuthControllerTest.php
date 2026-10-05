<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Modules\Auth\Models\User;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:keys');
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/user/register', [
            'name' => 'Test User',
            'email' => 'test_reg_' . uniqid() . '@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_register_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/user/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/auth/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_profile_includes_super_admin_permissions(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        Passport::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/auth/user/profile')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_super_admin' => true,
                    'permissions' => ['*'],
                ],
            ]);
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/auth/user/profile')->assertStatus(401);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $email = 'test_change_' . uniqid() . '@example.com';
        $user = User::query()->create([
            'name' => 'Change Password User',
            'email' => $email,
            'password' => 'OldPassword123!',
        ]);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->putJson('/api/v1/auth/user/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'Password changed successfully!',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/v1/auth/user/profile', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => 'New Name',
                ],
            ]);

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_authenticated_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->post('/api/v1/auth/user/profile', [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $this->assertNotNull($user->fresh()->avatarUrl());
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->postJson('/api/v1/auth/user/profile', ['name' => 'Hacker'])
            ->assertStatus(401);
    }

    public function test_user_can_request_forgot_password(): void
    {
        $email = 'test_forgot_' . uniqid() . '@example.com';
        User::query()->create([
            'name' => 'Forgot User',
            'email' => $email,
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/user/forgot-password', [
            'email' => $email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'We have e-mailed your password reset link!',
                ],
            ]);
    }

    public function test_user_can_reset_password(): void
    {
        $email = 'test_reset_' . uniqid() . '@example.com';
        $user = User::query()->create([
            'name' => 'Reset User',
            'email' => $email,
            'password' => 'OldPassword123!',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/user/reset-password', [
            'token' => $token,
            'email' => $email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'Your password has been reset!',
                ],
            ]);
    }

    public function test_user_can_verify_email(): void
    {
        Event::fake([Verified::class]);

        $email = 'test_verify_' . uniqid() . '@example.com';
        $user = User::query()->create([
            'name' => 'Verify User',
            'email' => $email,
            'password' => 'Password123!',
        ]);

        $hash = sha1($user->getEmailForVerification());

        $response = $this->postJson("/api/v1/auth/user/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'Your email has been verified.',
                ],
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_can_logout(): void
    {
        $email = 'test_logout_' . uniqid() . '@example.com';
        $user = User::query()->create([
            'name' => 'Logout User',
            'email' => $email,
            'password' => 'Password123!',
        ]);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/v1/auth/user/logout');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'message' => 'Successfully logged out',
                ],
            ]);
    }
}
