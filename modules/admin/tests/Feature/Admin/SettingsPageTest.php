<?php

namespace Modules\Admin\Tests\Feature\Admin;

use App\Contracts\Setting as SettingContract;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');
    }

    public function test_super_admin_can_view_settings_page(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin, 'web')
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::settings/Index', false)
                ->has('settings')
                ->has('locales')
            );
    }

    public function test_super_admin_can_update_settings(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin, 'web')
            ->put('/admin/settings', [
                'sitename' => 'My Site',
                'user_registration' => true,
            ])
            ->assertRedirect();

        $settings = app(SettingContract::class);
        $this->assertSame('My Site', $settings->get('sitename'));
        $this->assertTrue($settings->boolean('user_registration'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_settings_page_resolves_branding_media(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $media = MediaItem::factory()->create();
        app(SettingContract::class)->set('logo', $media->id);

        $this->actingAs($admin, 'web')
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::settings/Index', false)
                ->where('media.logo.id', $media->id)
            );
    }

    public function test_super_admin_can_update_branding(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $media = MediaItem::factory()->create();

        $this->actingAs($admin, 'web')
            ->put('/admin/settings', ['logo' => $media->id])
            ->assertRedirect();

        $this->assertSame($media->id, app(SettingContract::class)->get('logo'));
    }
}
