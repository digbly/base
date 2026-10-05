<?php

namespace Modules\Network\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Modules\Network\Tests\TestCase;

class DashboardPageTest extends TestCase
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

    protected function makeWebsite(array $attributes = []): Website
    {
        return Website::create(array_merge([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_super_admin_can_view_dashboard(): void
    {
        $this->makeWebsite(['status' => WebsiteStatus::ACTIVE]);
        $this->makeWebsite(['status' => WebsiteStatus::SUSPENDED]);

        $this->actingAs($this->admin(), 'web')
            ->get('/network')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Network::dashboard/Index', false)
                ->where('dashboard.stats.websites.total', 2)
                ->where('dashboard.stats.websites.active', 1)
                ->where('dashboard.stats.websites.suspended', 1)
                ->has('dashboard.recent_websites')
                ->has('dashboard.recent_users')
            );
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get('/network')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/network')->assertRedirect('/login');
    }
}
