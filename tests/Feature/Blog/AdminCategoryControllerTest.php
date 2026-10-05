<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Auth\Models\User;
use Modules\Blog\Models\Category;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');
    }

    protected function base(): string
    {
        return '/api/v1/admin/blog';
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    protected function makeCategory(array $translation = []): Category
    {
        $category = Category::factory()->create();

        $category->translations()->updateOrCreate(
            ['locale' => $translation['locale'] ?? 'en'],
            array_merge([
                'locale' => 'en',
                'name' => 'Default name',
                'slug' => 'default-'.uniqid(),
            ], $translation)
        );

        return $category;
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->base().'/categories')->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->base().'/categories')->assertForbidden();
    }

    public function test_index_returns_categories_with_post_counts(): void
    {
        Passport::actingAs($this->admin());
        Category::factory()->count(2)->create();

        $this->getJson($this->base().'/categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'parent_id', 'is_home', 'posts_count', 'translations'],
                ],
            ]);
    }

    public function test_store_creates_category_with_translations(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base().'/categories', [
            'is_home' => true,
            'translations' => [
                ['locale' => 'en', 'name' => 'News', 'slug' => 'news'],
                ['locale' => 'vi', 'name' => 'Tin tuc', 'slug' => 'tin-tuc'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'News')
            ->assertJsonPath('data.slug', 'news')
            ->assertJsonPath('data.is_home', true);

        $this->assertDatabaseHas('post_category_translations', [
            'locale' => 'vi',
            'slug' => 'tin-tuc',
        ]);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Passport::actingAs($this->admin());
        $this->makeCategory(['slug' => 'duplicate-slug']);

        $this->postJson($this->base().'/categories', [
            'translations' => [
                ['locale' => 'en', 'name' => 'Another', 'slug' => 'duplicate-slug'],
            ],
        ])->assertJsonValidationErrors('translations.0.slug');
    }

    public function test_update_changes_category(): void
    {
        Passport::actingAs($this->admin());
        $category = Category::factory()->create();

        $this->putJson($this->base()."/categories/{$category->getKey()}", [
            'translations' => [
                ['locale' => 'en', 'name' => 'Updated name', 'slug' => 'updated-name'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated name');

        $this->assertDatabaseHas('post_category_translations', [
            'post_category_id' => $category->getKey(),
            'slug' => 'updated-name',
        ]);
    }

    public function test_update_rejects_cycle(): void
    {
        Passport::actingAs($this->admin());
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->getKey()]);

        $this->putJson($this->base()."/categories/{$parent->getKey()}", [
            'parent_id' => $child->getKey(),
        ])->assertJsonValidationErrors('parent_id');

        $this->assertNull($parent->refresh()->parent_id);
    }

    public function test_destroy_deletes_category(): void
    {
        Passport::actingAs($this->admin());
        $category = Category::factory()->create();

        $this->deleteJson($this->base()."/categories/{$category->getKey()}")
            ->assertOk();

        $this->assertDatabaseMissing('post_categories', ['id' => $category->getKey()]);
    }
}
