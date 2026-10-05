<?php

namespace Modules\Admin\Tests\Feature\Menu;

use App\Models\Menus\Menu;
use App\Models\Menus\MenuItem;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Enums\MenuPermission;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class MenuBuilderApiTest extends TestCase
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

        Passport::actingAs($this->adminUser());
    }

    protected function menuUrl(string $suffix = ''): string
    {
        return "/api/v1/admin/websites/{$this->website->id}/menus{$suffix}";
    }

    protected function adminUser(): User
    {
        $role = Role::findOrCreate('admin', 'api');
        $role->syncPermissions(MenuPermission::values());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->menuUrl())->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->menuUrl())->assertForbidden();
    }

    public function test_store_creates_menu(): void
    {
        $response = $this->postJson($this->menuUrl(), ['name' => 'Main Menu']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Main Menu');

        $this->assertDatabaseHas('menus', ['name' => 'Main Menu', 'website_id' => $this->website->id]);
    }

    public function test_store_validates_name(): void
    {
        $this->postJson($this->menuUrl(), ['name' => ''])
            ->assertJsonValidationErrors('name');
    }

    public function test_update_syncs_items_tree_with_translations(): void
    {
        $menu = Menu::create(['name' => 'Main', 'website_id' => $this->website->id]);
        $menu->items()->create(['box_key' => 'custom', 'link' => '/stale', 'display_order' => 0]);

        $content = json_encode([
            ['label' => 'Home', 'link' => '/', 'is_home' => 1],
            [
                'label' => 'About',
                'link' => '/about',
                'children' => [
                    ['label' => 'Team', 'link' => '/about/team'],
                ],
            ],
        ]);

        $response = $this->putJson($this->menuUrl("/{$menu->id}"), [
            'name' => 'Main Updated',
            'content' => $content,
            'locale' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Main Updated')
            ->assertJsonCount(2, 'data.items');

        $menu->refresh();
        $this->assertSame('Main Updated', $menu->name);
        $this->assertSame(3, $menu->items()->count());
        $this->assertDatabaseMissing('menu_items', ['link' => '/stale']);

        $about = $menu->items()->where('box_key', 'custom')->where('link', '/about')->firstOrFail();
        $this->assertSame(1, $about->children()->count());
        $this->assertSame('Team', $about->children()->first()->label);
        $this->assertDatabaseHas('menu_item_translations', [
            'menu_item_id' => $about->id,
            'locale' => 'en',
            'label' => 'About',
        ]);
    }

    public function test_show_returns_items_tree(): void
    {
        $menu = Menu::create(['name' => 'Main', 'website_id' => $this->website->id]);
        $root = $menu->items()->create(['box_key' => 'custom', 'link' => '/', 'display_order' => 1]);
        $root->translateOrNew('en')->label = 'Home';
        $root->save();

        $this->getJson($this->menuUrl("/{$menu->id}"))
            ->assertOk()
            ->assertJsonPath('data.items.0.label', 'Home')
            ->assertJsonPath('data.items.0.is_custom', true);
    }

    public function test_destroy_deletes_menu(): void
    {
        $menu = Menu::create(['name' => 'Main', 'website_id' => $this->website->id]);

        $this->deleteJson($this->menuUrl("/{$menu->id}"))->assertOk();

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_menu_is_scoped_to_route_website(): void
    {
        $other = Website::create([
            'title' => 'Other Site',
            'subdomain' => 'other-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        $menu = Menu::create(['name' => 'Main', 'website_id' => $this->website->id]);

        $this->deleteJson("/api/v1/admin/websites/{$other->id}/menus/{$menu->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('menus', ['id' => $menu->id]);
    }
}
