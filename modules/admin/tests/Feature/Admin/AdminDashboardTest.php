<?php

namespace Modules\Admin\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function makeWebsite(array $attributes = []): Website
    {
        return Website::create(array_merge([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_super_admin_is_redirected_to_first_website(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertRedirect('/admin/'.$website->id);
    }

    public function test_super_admin_can_view_dashboard(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $website = $this->makeWebsite();

        $this->actingAs($user, 'web')
            ->get('/admin/'.$website->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::dashboard/Index', false)
                ->has('stats', 3)
                ->has('admin_menu')
            );
    }

    public function test_member_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $website = $this->makeWebsite();
        $website->users()->attach($user);

        $this->actingAs($user, 'web')
            ->get('/admin/'.$website->id)
            ->assertOk();
    }

    public function test_non_member_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();
        $website = $this->makeWebsite();

        $this->actingAs($user, 'web')
            ->get('/admin/'.$website->id)
            ->assertForbidden();
    }

    public function test_unknown_website_returns_not_found(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($user, 'web')
            ->get('/admin/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_user_without_accessible_website_sees_no_website_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('NoWebsite'));
    }
}
