<?php

namespace Modules\Admin\Tests\Feature\Menu;

use App\Models\Menus\Menu;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Enums\MenuPermission;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

class MenuBoxesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');

        Passport::actingAs($this->adminUser());
    }

    protected function menuUrl(string $suffix = ''): string
    {
        return "/api/v1/admin/menus{$suffix}";
    }

    protected function adminUser(): User
    {
        $role = Role::findOrCreate('admin', 'api');
        $role->syncPermissions(MenuPermission::values());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_boxes_lists_registered_content_sources(): void
    {
        $keys = collect($this->getJson($this->menuUrl('/boxes'))->assertOk()->json('data'))
            ->pluck('key');

        $this->assertTrue($keys->contains('posts'));
        $this->assertTrue($keys->contains('post-categories'));
    }

    public function test_box_items_returns_posts_with_translated_titles(): void
    {
        $post = Post::factory()->create();

        $results = $this->getJson($this->menuUrl('/boxes/posts'))->assertOk()->json('results');

        $result = collect($results)->firstWhere('id', $post->id);

        $this->assertNotNull($result);
        $this->assertSame(
            $post->translations()->where('locale', 'en')->value('title'),
            $result['text']
        );
    }

    public function test_box_items_search_filters_by_translated_title(): void
    {
        $match = Post::factory()->create();
        $match->translations()->where('locale', 'en')->update(['title' => 'Zzqxnine report']);

        $other = Post::factory()->create();

        $ids = array_column(
            $this->getJson($this->menuUrl('/boxes/posts?q=Zzqxnine'))->assertOk()->json('results'),
            'id'
        );

        $this->assertContains($match->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_box_items_returns_categories_with_translated_names(): void
    {
        $category = Category::factory()->create();

        $results = $this->getJson($this->menuUrl('/boxes/post-categories'))->assertOk()->json('results');

        $result = collect($results)->firstWhere('id', $category->id);

        $this->assertNotNull($result);
        $this->assertSame(
            $category->translations()->where('locale', 'en')->value('name'),
            $result['text']
        );
    }

    public function test_unknown_box_returns_empty_results(): void
    {
        $this->getJson($this->menuUrl('/boxes/missing'))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_locations_lists_registered_menu_locations(): void
    {
        $keys = collect($this->getJson($this->menuUrl('/locations'))->assertOk()->json('data'))
            ->pluck('key');

        $this->assertTrue($keys->contains('primary'));
        $this->assertTrue($keys->contains('footer'));
    }

    public function test_update_persists_location_assignments(): void
    {
        $menu = Menu::create(['name' => 'Main']);

        $this->putJson($this->menuUrl("/{$menu->id}"), [
            'name' => 'Main',
            'content' => '[]',
            'locale' => 'en',
            'location' => ['primary'],
        ])->assertOk();

        $this->getJson($this->menuUrl('/locations'))
            ->assertJsonPath('selected.primary', $menu->id);

        $this->putJson($this->menuUrl("/{$menu->id}"), [
            'name' => 'Main',
            'content' => '[]',
            'locale' => 'en',
            'location' => [],
        ])->assertOk();

        $selected = $this->getJson($this->menuUrl('/locations'))->json('selected');

        $this->assertArrayNotHasKey('primary', $selected);
    }

    public function test_box_endpoints_require_view_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->menuUrl('/boxes'))->assertForbidden();
        $this->getJson($this->menuUrl('/boxes/posts'))->assertForbidden();
        $this->getJson($this->menuUrl('/locations'))->assertForbidden();
    }
}
