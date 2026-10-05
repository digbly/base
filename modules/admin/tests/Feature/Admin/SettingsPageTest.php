<?php

namespace Modules\Admin\Tests\Feature\Admin;

use App\Contracts\Setting as SettingContract;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class SettingsPageTest extends TestCase
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
        $website = Website::create([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        config(['app.website_id' => $website->id]);

        return $website;
    }

    public function test_super_admin_can_view_settings_page(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($admin, 'web')
            ->get('/admin/'.$website->id.'/settings')
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
        $website = $this->makeWebsite();

        $this->actingAs($admin, 'web')
            ->put('/admin/'.$website->id.'/settings', [
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
        $website = $this->makeWebsite();
        $website->users()->attach($user);

        $this->actingAs($user, 'web')
            ->get('/admin/'.$website->id.'/settings')
            ->assertForbidden();
    }

    public function test_settings_page_resolves_branding_media(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $media = MediaItem::factory()->create();
        app(SettingContract::class)->set('logo', $media->id);

        $this->actingAs($admin, 'web')
            ->get('/admin/'.$website->id.'/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::settings/Index', false)
                ->where('media.logo.id', $media->id)
            );
    }

    public function test_super_admin_can_update_branding(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();
        $media = MediaItem::factory()->create();

        $this->actingAs($admin, 'web')
            ->put('/admin/'.$website->id.'/settings', ['logo' => $media->id])
            ->assertRedirect();

        $this->assertSame($media->id, app(SettingContract::class)->get('logo'));
    }
}
