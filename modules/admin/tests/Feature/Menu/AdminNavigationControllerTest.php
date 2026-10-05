<?php

namespace Modules\Admin\Tests\Feature\Menu;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class AdminNavigationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');

        $this->website = Website::create([
            'title' => 'Test Site',
            'subdomain' => 'test-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    protected function url(): string
    {
        return "/api/v1/admin/websites/{$this->website->id}/navigation";
    }

    public function test_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_returns_the_registered_navigation_tree(): void
    {
        Passport::actingAs(User::factory()->create(['is_super_admin' => true]));

        $data = $this->getJson($this->url())->assertOk()->json('data');

        $this->assertSame('dashboard', $data[0]['id']);
        $this->assertSame('/dashboard', $data[0]['to']);
        $this->assertSame('layout-dashboard', $data[0]['icon']);
        $this->assertSame('dashboard.view', $data[0]['permission']);

        $blog = collect($data)->firstWhere('id', 'blog');

        $this->assertNotNull($blog);
        $this->assertSame(
            ['blog-posts', 'blog-categories', 'blog-comments'],
            array_column($blog['children'], 'id')
        );
    }

    public function test_labels_follow_the_request_locale(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->url(), ['Accept-Language' => 'vi'])
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Bảng điều khiển');
    }
}
