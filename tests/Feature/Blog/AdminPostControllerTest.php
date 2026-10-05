<?php

namespace Tests\Feature\Blog;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Auth\Models\User;
use Modules\Blog\Enums\PostStatus;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Tests\TestCase;

class AdminPostControllerTest extends TestCase
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

        config(['app.website_id' => $this->website->id]);
    }

    protected function base(): string
    {
        return "/api/v1/admin/websites/{$this->website->id}/blog";
    }

    protected function otherWebsite(): Website
    {
        return Website::create([
            'title' => 'Other Site',
            'subdomain' => 'other-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    protected function editor(): User
    {
        $role = Role::findOrCreate('blog-editor', 'api');
        $role->syncPermissions(['posts.view', 'posts.create', 'posts.update', 'posts.delete', 'categories.view']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function makePost(array $translation = [], array $attributes = []): Post
    {
        $post = Post::factory()->create($attributes);

        $post->translations()->updateOrCreate(
            ['locale' => $translation['locale'] ?? 'en'],
            array_merge([
                'locale' => 'en',
                'title' => 'Default title',
                'slug' => 'default-'.uniqid(),
                'content' => 'Default content',
            ], $translation)
        );

        return $post;
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'status' => PostStatus::Published->value,
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Hello world',
                    'slug' => 'hello-world',
                    'description' => 'A short description',
                    'content' => 'The content',
                ],
            ],
        ], $overrides);
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->base().'/posts')->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->base().'/posts')->assertForbidden();
    }

    public function test_index_returns_paginated_posts(): void
    {
        Passport::actingAs($this->admin());
        Post::factory()->count(3)->create();

        $this->getJson($this->base().'/posts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'slug', 'status', 'categories', 'translations', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        Passport::actingAs($this->admin());

        $this->makePost(['title' => 'Laravel tips', 'slug' => 'laravel-tips']);
        Post::factory()->draft()->create();

        $this->getJson($this->base().'/posts?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson($this->base().'/posts?search=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel tips');
    }

    public function test_index_only_returns_posts_of_current_website(): void
    {
        Passport::actingAs($this->admin());

        $this->makePost(['title' => 'Mine', 'slug' => 'mine']);

        config(['app.website_id' => $this->otherWebsite()->id]);
        $this->makePost(['title' => 'Theirs', 'slug' => 'theirs']);

        // The website context must be resolved from the route, not from config.
        config(['app.website_id' => null]);

        $this->getJson($this->base().'/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine');
    }

    public function test_show_returns_not_found_for_post_of_another_website(): void
    {
        Passport::actingAs($this->admin());

        config(['app.website_id' => $this->otherWebsite()->id]);
        $post = $this->makePost(['title' => 'Theirs', 'slug' => 'theirs']);

        // The website context must be resolved from the route, not from config.
        config(['app.website_id' => null]);

        $this->getJson($this->base()."/posts/{$post->getKey()}")
            ->assertNotFound();
    }

    public function test_store_creates_post_with_translations_and_categories(): void
    {
        Passport::actingAs($this->admin());
        $category = Category::factory()->create();

        $response = $this->postJson($this->base().'/posts', $this->payload([
            'categories' => [$category->getKey()],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Hello world')
            ->assertJsonPath('data.slug', 'hello-world')
            ->assertJsonPath('data.status', 'published');

        $post = Post::query()->firstOrFail();
        $this->assertDatabaseHas('post_translations', [
            'post_id' => $post->getKey(),
            'locale' => 'en',
            'slug' => 'hello-world',
        ]);
        $this->assertTrue($post->categories()->whereKey($category->getKey())->exists());
    }

    public function test_store_validates_payload(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base().'/posts', [
            'status' => 'invalid',
            'translations' => [],
        ])->assertJsonValidationErrors(['status', 'translations']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Passport::actingAs($this->admin());
        $this->makePost(['title' => 'Taken', 'slug' => 'taken-slug']);

        $this->postJson($this->base().'/posts', $this->payload([
            'translations' => [
                ['locale' => 'en', 'title' => 'Another', 'slug' => 'taken-slug'],
            ],
        ]))->assertJsonValidationErrors('translations.0.slug');
    }

    public function test_store_rejects_duplicate_slug_across_locales(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base().'/posts', $this->payload([
            'translations' => [
                ['locale' => 'en', 'title' => 'English', 'slug' => 'shared-slug'],
                ['locale' => 'vi', 'title' => 'Vietnamese', 'slug' => 'shared-slug'],
            ],
        ]))->assertJsonValidationErrors('translations.1.slug');

        $this->assertDatabaseMissing('post_translations', ['slug' => 'shared-slug']);
    }

    public function test_update_changes_status_and_translations(): void
    {
        Passport::actingAs($this->admin());
        $post = Post::factory()->create();

        $this->putJson($this->base()."/posts/{$post->getKey()}", [
            'status' => PostStatus::Draft->value,
            'translations' => [
                ['locale' => 'en', 'title' => 'Updated title', 'slug' => 'updated-title'],
                ['locale' => 'vi', 'title' => 'Tieu de', 'slug' => 'tieu-de'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Updated title');

        $this->assertDatabaseHas('post_translations', [
            'post_id' => $post->getKey(),
            'locale' => 'vi',
            'title' => 'Tieu de',
        ]);
    }

    public function test_destroy_deletes_post(): void
    {
        Passport::actingAs($this->admin());
        $post = Post::factory()->create();

        $this->deleteJson($this->base()."/posts/{$post->getKey()}")
            ->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $post->getKey()]);
        $this->assertDatabaseMissing('post_translations', ['post_id' => $post->getKey()]);
    }

    public function test_editor_with_permission_can_create_post(): void
    {
        Passport::actingAs($this->editor());

        $this->postJson($this->base().'/posts', $this->payload())
            ->assertCreated();
    }
}
