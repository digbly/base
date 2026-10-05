<?php

namespace Modules\Admin\Tests\Feature\Admin;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');
    }

    protected function base(): string
    {
        return '/api/v1/admin';
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    protected function makeRole(string $name, array $permissions = []): Role
    {
        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => 'api',
        ]);

        $role->syncPermissions($permissions);

        return $role;
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->base().'/roles')->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->base().'/roles')->assertForbidden();
    }

    public function test_index_lists_roles_with_permissions(): void
    {
        Passport::actingAs($this->admin());
        $this->makeRole('editor', ['users.manage']);

        $this->getJson($this->base().'/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'editor'])
            ->assertJsonPath('data.0.permissions', ['users.manage']);
    }

    public function test_store_creates_role_with_permissions(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base().'/roles', [
            'name' => 'manager',
            'permissions' => ['users.manage', 'roles.manage'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'manager')
            ->assertJsonPath('data.permissions', ['users.manage', 'roles.manage']);

        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
        ]);
    }

    public function test_store_validates_unique_name(): void
    {
        Passport::actingAs($this->admin());
        $this->makeRole('manager');

        $this->postJson($this->base().'/roles', ['name' => 'manager'])
            ->assertJsonValidationErrors('name');
    }

    public function test_store_validates_permissions_exist(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base().'/roles', [
            'name' => 'manager',
            'permissions' => ['missing.permission'],
        ])->assertJsonValidationErrors('permissions.0');
    }

    public function test_show_returns_role_with_permissions(): void
    {
        Passport::actingAs($this->admin());
        $role = $this->makeRole('editor', ['users.manage']);

        $this->getJson($this->base()."/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'editor')
            ->assertJsonPath('data.permissions', ['users.manage']);
    }

    public function test_update_renames_and_syncs_permissions(): void
    {
        Passport::actingAs($this->admin());
        $role = $this->makeRole('editor', ['users.manage']);

        $this->putJson($this->base()."/roles/{$role->id}", [
            'name' => 'editor-updated',
            'permissions' => ['roles.manage'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'editor-updated')
            ->assertJsonPath('data.permissions', ['roles.manage']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'editor-updated']);
    }

    public function test_update_without_permissions_preserves_existing(): void
    {
        Passport::actingAs($this->admin());
        $role = $this->makeRole('editor', ['users.manage']);

        $this->putJson($this->base()."/roles/{$role->id}", ['name' => 'editor-renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'editor-renamed')
            ->assertJsonPath('data.permissions', ['users.manage']);
    }

    public function test_destroy_deletes_role(): void
    {
        Passport::actingAs($this->admin());
        $role = $this->makeRole('editor');

        $this->deleteJson($this->base()."/roles/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_rejects_role_assigned_to_users(): void
    {
        Passport::actingAs($this->admin());
        $role = $this->makeRole('editor');
        User::factory()->create()->assignRole($role);

        $this->deleteJson($this->base()."/roles/{$role->id}")
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_permissions_endpoint_lists_permissions(): void
    {
        Passport::actingAs($this->admin());

        $this->getJson($this->base().'/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'users.manage'])
            ->assertJsonFragment(['name' => 'roles.manage']);
    }
}
