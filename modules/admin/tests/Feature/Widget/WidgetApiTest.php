<?php

namespace Modules\Admin\Tests\Feature\Widget;

use App\Models\Role;
use App\Models\ThemeSidebar;
use App\Themes\FileRepository;
use App\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Enums\WidgetPermission;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Themes\Default\Providers\ThemeServiceProvider;

class WidgetApiTest extends TestCase
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

    protected function widgetUrl(string $suffix = ''): string
    {
        return "/api/v1/admin/widgets{$suffix}";
    }

    protected function adminUser(): User
    {
        $role = Role::findOrCreate('admin', 'api');
        $role->syncPermissions(WidgetPermission::values());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->widgetUrl())->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->widgetUrl())->assertForbidden();
    }

    public function test_index_returns_widgets_sidebars_and_assignments(): void
    {
        $response = $this->getJson($this->widgetUrl())->assertOk();

        $response->assertJsonPath('data.theme', 'default')
            ->assertJsonFragment(['key' => 'categories'])
            ->assertJsonFragment(['key' => 'sidebar']);

        $this->assertContains(
            'recent-posts',
            array_column($response->json('data.widgets'), 'key')
        );

        $this->assertSame([], $response->json('data.sidebar_widgets.sidebar'));
    }

    public function test_update_syncs_widgets_with_translations_and_order(): void
    {
        $response = $this->putJson($this->widgetUrl('/sidebar'), [
            'locale' => 'en',
            'content' => [
                ['widget' => 'recent-posts', 'label' => 'Fresh', 'data' => ['limit' => 3]],
                ['widget' => 'categories', 'label' => 'Topics'],
            ],
        ])->assertOk();

        $response->assertJsonPath('message', 'Sidebar saved successfully.');

        $this->assertDatabaseHas('theme_sidebars', [
            'sidebar' => 'sidebar',
            'widget' => 'recent-posts',
            'display_order' => 1,
        ]);

        $recent = ThemeSidebar::query()->where('widget', 'recent-posts')->firstOrFail();
        $this->assertSame(3, $recent->data['limit']);
        $this->assertDatabaseHas('theme_sidebar_translations', [
            'theme_sidebar_id' => $recent->id,
            'locale' => 'en',
            'label' => 'Fresh',
        ]);

        // Re-save with only the recent-posts widget reordered: categories must be removed.
        $this->putJson($this->widgetUrl('/sidebar'), [
            'locale' => 'en',
            'content' => [
                ['id' => $recent->id, 'widget' => 'recent-posts', 'label' => 'Fresh', 'data' => ['limit' => 5]],
            ],
        ])->assertOk();

        $this->assertSame(1, ThemeSidebar::query()->where('sidebar', 'sidebar')->count());
        $this->assertDatabaseMissing('theme_sidebars', ['widget' => 'categories']);
        $this->assertSame(5, $recent->fresh()->data['limit']);
    }

    public function test_update_rejects_unknown_sidebar(): void
    {
        $this->putJson($this->widgetUrl('/unknown'), [
            'content' => [],
        ])->assertNotFound();
    }
}
