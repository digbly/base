<?php

namespace Modules\Admin\Tests\Feature\Admin;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Enums\Permission;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');
    }

    protected function makeWebsite(): Website
    {
        return Website::create([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_super_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($admin, 'web')
            ->get('/admin/'.$website->id.'/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::users/Index', false)
                ->has('users.data')
                ->has('roles')
            );
    }

    public function test_super_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($admin, 'web')
            ->post('/admin/'.$website->id.'/users', [
                'name' => 'Created User',
                'email' => 'created@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [],
                'is_super_admin' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'created@example.com']);
    }

    public function test_store_validates_unique_email(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($admin, 'web')
            ->post('/admin/'.$website->id.'/users', [
                'name' => 'Dup',
                'email' => $admin->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin, 'web')
            ->put('/admin/'.$website->id.'/users/'.$target->id, [
                'name' => 'New Name',
                'email' => $target->email,
            ])
            ->assertRedirect();

        $this->assertSame('New Name', $target->fresh()->name);
    }

    public function test_super_admin_can_reset_user_password(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();
        $target = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->put('/admin/'.$website->id.'/users/'.$target->id.'/password', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword123!', $target->fresh()->password));
    }

    public function test_super_admin_can_delete_and_restore_user(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();
        $target = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->delete('/admin/'.$website->id.'/users/'.$target->id)
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->actingAs($admin, 'web')
            ->post('/admin/'.$website->id.'/users/'.$target->id.'/restore')
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $website = $this->makeWebsite();
        $website->users()->attach($user);

        $this->actingAs($user, 'web')
            ->get('/admin/'.$website->id.'/users')
            ->assertForbidden();
    }

    public function test_admin_with_permission_can_list_users(): void
    {
        $actor = $this->userWithPermission();
        $website = $this->makeWebsite();
        $website->users()->attach($actor);

        $this->actingAs($actor, 'web')
            ->get('/admin/'.$website->id.'/users')
            ->assertOk();
    }

    public function test_non_super_admin_cannot_update_a_super_admin(): void
    {
        $actor = $this->userWithPermission();
        $website = $this->makeWebsite();
        $website->users()->attach($actor);

        $target = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($actor, 'web')
            ->put('/admin/'.$website->id.'/users/'.$target->id, [
                'name' => 'Hacked',
                'email' => $target->email,
            ])
            ->assertSessionHasErrors('user');
    }

    public function test_non_super_admin_cannot_restore_a_super_admin(): void
    {
        $actor = $this->userWithPermission();
        $website = $this->makeWebsite();
        $website->users()->attach($actor);

        $target = User::factory()->create(['is_super_admin' => true]);
        $target->delete();

        $this->actingAs($actor, 'web')
            ->post('/admin/'.$website->id.'/users/'.$target->id.'/restore')
            ->assertSessionHasErrors('user');
    }

    protected function userWithPermission(): User
    {
        $role = Role::findOrCreate('user-manager', 'api');
        $role->syncPermissions([Permission::UsersManage->value]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
