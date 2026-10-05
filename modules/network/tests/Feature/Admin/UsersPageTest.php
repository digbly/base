<?php

namespace Modules\Network\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Auth\Models\User;
use Modules\Network\Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_super_admin_can_view_users(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->get('/network/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Network::users/Index', false)
                ->has('users.data')
                ->has('roles')
                ->has('selfId')
            );
    }

    public function test_super_admin_can_create_user(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/network/users', [
                'name' => 'Created User',
                'email' => 'network-created@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [],
                'is_super_admin' => false,
            ])
            ->assertRedirect(route('admin.network.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'network-created@example.com']);
    }

    public function test_store_validates_unique_email(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->admin(), 'web')
            ->post('/network/users', [
                'name' => 'Dup',
                'email' => $existing->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_update_user(): void
    {
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin(), 'web')
            ->put('/network/users/'.$target->id, [
                'name' => 'New Name',
                'email' => $target->email,
            ])
            ->assertRedirect();

        $this->assertSame('New Name', $target->fresh()->name);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->delete('/network/users/'.$admin->id)
            ->assertSessionHasErrors('user');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_super_admin_cannot_revoke_own_super_admin_access(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->put('/network/users/'.$admin->id, [
                'name' => $admin->name,
                'email' => $admin->email,
                'is_super_admin' => false,
            ])
            ->assertSessionHasErrors('is_super_admin');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_super_admin_can_reset_password(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin(), 'web')
            ->put('/network/users/'.$target->id.'/password', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword123!', $target->fresh()->password));
    }

    public function test_super_admin_can_delete_and_restore_user(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin(), 'web')
            ->delete('/network/users/'.$target->id)
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->actingAs($this->admin(), 'web')
            ->post('/network/users/'.$target->id.'/restore')
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get('/network/users')
            ->assertForbidden();
    }
}
