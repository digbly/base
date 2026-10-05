<?php

namespace Modules\Admin\Tests\Feature\Permission;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Tests\TestCase;

class WebsitePermissionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.website_id' => null]);
    }

    public function test_generate_creates_global_permissions(): void
    {
        $this->artisan('permission:generate')->assertSuccessful();

        $this->assertDatabaseHas('permissions', [
            'name' => 'menus.view',
            'guard_name' => 'api',
            'website_id' => null,
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'users.manage',
            'guard_name' => 'api',
            'website_id' => null,
        ]);
    }

    public function test_global_permissions_are_visible_to_every_website(): void
    {
        $this->artisan('permission:generate');

        config(['app.website_id' => 'site-1']);
        $this->assertTrue(Permission::query()->where('name', 'menus.view')->exists());

        config(['app.website_id' => 'site-2']);
        $this->assertTrue(Permission::query()->where('name', 'menus.view')->exists());
    }

    public function test_website_scoped_permissions_are_isolated(): void
    {
        config(['app.website_id' => 'site-1']);
        Permission::query()->create([
            'name' => 'custom.manage',
            'guard_name' => 'api',
            'website_id' => 'site-1',
        ]);

        config(['app.website_id' => 'site-2']);
        $this->assertFalse(Permission::query()->where('name', 'custom.manage')->exists());
        $this->assertDatabaseMissing('permissions', [
            'name' => 'custom.manage',
            'website_id' => 'site-2',
        ]);
    }

    public function test_same_permission_name_can_exist_per_website(): void
    {
        foreach (['site-1', 'site-2'] as $websiteId) {
            config(['app.website_id' => $websiteId]);
            Permission::query()->create([
                'name' => 'custom.manage',
                'guard_name' => 'api',
                'website_id' => $websiteId,
            ]);
        }

        $this->assertSame(
            2,
            Permission::withoutGlobalScope('website_id')->where('name', 'custom.manage')->count()
        );
    }

    public function test_role_is_scoped_per_website(): void
    {
        config(['app.website_id' => 'site-1']);
        Role::query()->create(['name' => 'editor', 'guard_name' => 'api', 'website_id' => 'site-1']);

        config(['app.website_id' => 'site-2']);
        $this->assertFalse(Role::query()->where('name', 'editor')->exists());
    }
}
