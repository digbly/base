<?php

namespace Modules\Admin\Tests\Feature\Admin;

use App\Models\Pages\Page;
use App\Themes\FileRepository;
use App\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Themes\Default\Providers\ThemeServiceProvider;

class CustomizePageTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');

        $this->app->register(ThemeServiceProvider::class);
        $this->app->make(ThemeManager::class)->activate(
            $this->app->make(FileRepository::class)->findOrFail('Default')
        );

        $this->website = Website::create([
            'title' => 'Test Site',
            'subdomain' => 'test-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        config(['app.website_id' => $this->website->id]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    protected function base(): string
    {
        return '/admin/'.$this->website->id.'/customize';
    }

    protected function makePage(string $title = 'Home', string $template = 'landing'): Page
    {
        $page = Page::create([
            'status' => 'published',
            'template' => $template,
            'website_id' => $this->website->id,
        ]);

        $page->translateOrNew('en')->title = $title;
        $page->translateOrNew('en')->slug = strtolower($title);
        $page->save();

        return $page->fresh('translations');
    }

    public function test_super_admin_can_view_customizer(): void
    {
        $page = $this->makePage('About');

        $this->actingAs($this->admin(), 'web')
            ->get($this->base())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin::customize/Index', false)
                ->has('panels')
                ->has('settings.setting')
                ->has('pages')
                ->has('widgets')
                ->has('abilities')
                ->where('theme', 'default')
            );
    }

    public function test_super_admin_can_save_settings_blocks_and_widgets(): void
    {
        $page = $this->makePage('Home');

        $this->actingAs($this->admin(), 'web')
            ->post($this->base(), [
                'locale' => 'en',
                'setting' => ['sitename' => 'My Site'],
                'theme_setting' => ['home_page' => $page->id],
                'blocks' => [
                    'content' => [['block' => 'hero', 'label' => 'Hero', 'data' => ['title' => 'Welcome']]],
                ],
                'widgets' => [
                    'sidebar' => [['widget' => 'recent-posts', 'label' => 'Fresh', 'data' => ['limit' => 3]]],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('page_blocks', [
            'page_id' => $page->id,
            'block' => 'hero',
            'display_order' => 1,
        ]);

        $this->assertDatabaseHas('theme_sidebars', [
            'website_id' => $this->website->id,
            'sidebar' => 'sidebar',
            'widget' => 'recent-posts',
        ]);
    }

    public function test_page_blocks_endpoint_returns_grouped_blocks(): void
    {
        $page = $this->makePage('Home');

        $this->actingAs($this->admin(), 'web')
            ->post($this->base(), [
                'theme_setting' => ['home_page' => $page->id],
                'blocks' => ['content' => [['block' => 'hero', 'label' => 'Hero', 'data' => ['title' => 'Hi']]]],
            ])
            ->assertRedirect();

        $this->actingAs($this->admin(), 'web')
            ->get($this->base().'/page-blocks/'.$page->id)
            ->assertOk()
            ->assertJsonPath('template', 'landing')
            ->assertJsonPath('blocks.content.0.block', 'hero');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->website->users()->attach($user);

        $this->actingAs($user, 'web')
            ->get($this->base())
            ->assertForbidden();
    }
}
