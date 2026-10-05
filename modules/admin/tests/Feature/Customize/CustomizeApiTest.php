<?php

namespace Modules\Admin\Tests\Feature\Customize;

use App\Models\Pages\Page;
use App\Models\Role;
use App\Themes\FileRepository;
use App\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Enums\PagePermission;
use Modules\Admin\Enums\ThemePermission;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Themes\Default\Providers\ThemeServiceProvider;

class CustomizeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');

        $this->app->register(ThemeServiceProvider::class);
        $this->app->make(ThemeManager::class)->activate(
            $this->app->make(FileRepository::class)->findOrFail('Default')
        );

        Passport::actingAs($this->adminUser());
    }

    protected function customizeUrl(string $suffix = ''): string
    {
        return "/api/v1/admin/customize{$suffix}";
    }

    protected function adminUser(): User
    {
        $role = Role::findOrCreate('admin', 'api');
        $role->syncPermissions([
            ...ThemePermission::values(),
            ...PagePermission::values(),
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function makePage(string $title = 'Home', string $template = 'landing'): Page
    {
        $page = Page::create([
            'status' => 'published',
            'template' => $template,
        ]);

        $page->translateOrNew('en')->title = $title;
        $page->translateOrNew('en')->slug = strtolower($title);
        $page->save();

        return $page->fresh('translations');
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->customizeUrl())->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->customizeUrl())->assertForbidden();
    }

    public function test_index_returns_panels_settings_and_pages(): void
    {
        $page = $this->makePage('About', 'landing');

        $response = $this->getJson($this->customizeUrl())->assertOk();

        $response->assertJsonPath('data.theme', 'default')
            ->assertJsonFragment(['id' => $page->id, 'title' => 'About'])
            ->assertJsonFragment(['key' => 'site_identity'])
            ->assertJsonFragment(['key' => 'home_page'])
            ->assertJsonFragment(['key' => 'hero']);
    }

    public function test_update_saves_settings_blocks_and_widgets(): void
    {
        $page = $this->makePage('Home');

        $this->postJson($this->customizeUrl(), [
            'locale' => 'en',
            'setting' => ['sitename' => 'My Site'],
            'theme_setting' => ['home_page' => $page->id],
            'blocks' => [
                'content' => [
                    ['block' => 'hero', 'label' => 'Hero', 'data' => ['title' => 'Welcome']],
                    ['block' => 'posts', 'label' => 'Latest', 'data' => []],
                ],
            ],
            'widgets' => [
                'sidebar' => [
                    ['widget' => 'recent-posts', 'label' => 'Fresh', 'data' => ['limit' => 3]],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('theme_settings', [
            'code' => 'home_page',
        ]);

        $this->assertDatabaseHas('page_blocks', [
            'page_id' => $page->id,
            'block' => 'hero',
            'display_order' => 1,
        ]);

        $this->assertDatabaseHas('page_blocks', [
            'page_id' => $page->id,
            'block' => 'posts',
            'display_order' => 2,
        ]);

        $this->assertDatabaseHas('theme_sidebars', [
            'sidebar' => 'sidebar',
            'widget' => 'recent-posts',
        ]);
    }

    public function test_update_removes_blocks_no_longer_present(): void
    {
        $page = $this->makePage('Home');

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $page->id],
            'blocks' => [
                'content' => [['block' => 'hero', 'label' => 'Hero', 'data' => []]],
            ],
        ])->assertOk();

        $this->assertSame(1, $page->blocks()->count());

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $page->id],
            'blocks' => ['content' => []],
        ])->assertOk();

        $this->assertSame(0, $page->blocks()->count());
    }

    public function test_page_blocks_endpoint_returns_grouped_blocks(): void
    {
        $page = $this->makePage('Home');

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $page->id],
            'blocks' => [
                'content' => [['block' => 'hero', 'label' => 'Hero', 'data' => ['title' => 'Hi']]],
            ],
        ])->assertOk();

        $response = $this->getJson($this->customizeUrl("/page-blocks/{$page->id}"))->assertOk();

        $response->assertJsonPath('template', 'landing')
            ->assertJsonPath('blocks.content.0.block', 'hero')
            ->assertJsonPath('blocks.content.0.data.title', 'Hi');
    }

    public function test_widgets_endpoint_returns_assignments(): void
    {
        $response = $this->getJson($this->customizeUrl('/widgets'))->assertOk();

        $response->assertJsonPath('theme', 'default')
            ->assertJsonFragment(['key' => 'sidebar']);
    }

    public function test_update_ignores_block_ids_owned_by_another_page(): void
    {
        $pageA = $this->makePage('Page A');
        $pageB = $this->makePage('Page B');

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $pageA->id],
            'blocks' => [
                'content' => [['block' => 'hero', 'label' => 'Hero A', 'data' => []]],
            ],
        ])->assertOk();

        $foreignBlockId = $pageA->blocks()->firstOrFail()->id;

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $pageB->id],
            'blocks' => [
                'content' => [
                    ['id' => $foreignBlockId, 'block' => 'posts', 'label' => 'Posts B', 'data' => []],
                ],
            ],
        ])->assertOk();

        // The block owned by page A must remain untouched.
        $this->assertSame(1, $pageA->blocks()->count());
        $this->assertDatabaseHas('page_blocks', [
            'id' => $foreignBlockId,
            'page_id' => $pageA->id,
            'block' => 'hero',
        ]);

        // Page B must receive a brand new block, not reuse the foreign id.
        $this->assertSame(1, $pageB->blocks()->count());
        $this->assertNotSame($foreignBlockId, $pageB->blocks()->firstOrFail()->id);
    }

    public function test_update_ignores_unknown_blocks_and_sidebars(): void
    {
        $page = $this->makePage('Home');

        $this->postJson($this->customizeUrl(), [
            'theme_setting' => ['home_page' => $page->id],
            'blocks' => [
                'content' => [['block' => 'does-not-exist', 'label' => 'Ghost', 'data' => []]],
            ],
            'widgets' => [
                'unknown-sidebar' => [['widget' => 'recent-posts', 'data' => []]],
            ],
        ])->assertOk();

        $this->assertSame(0, $page->blocks()->count());
        $this->assertDatabaseCount('theme_sidebars', 0);
    }
}
