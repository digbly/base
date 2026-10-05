<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Enums\CommentStatus;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use Tests\TestCase;

class PublicBlogApiTest extends TestCase
{
    use RefreshDatabase;

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

    protected function makeCategory(array $translation = []): Category
    {
        $category = Category::factory()->create();

        $category->translations()->updateOrCreate(
            ['locale' => $translation['locale'] ?? 'en'],
            array_merge([
                'locale' => 'en',
                'name' => 'Default',
                'slug' => 'default-'.uniqid(),
            ], $translation)
        );

        return $category;
    }

    public function test_posts_index_returns_only_published_posts(): void
    {
        $this->makePost(['title' => 'Published post', 'slug' => 'published-post']);
        Post::factory()->draft()->create();

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published post');
    }

    public function test_posts_index_filters_by_category_slug(): void
    {
        $category = $this->makeCategory(['name' => 'News', 'slug' => 'news']);
        $post = $this->makePost(['title' => 'In category', 'slug' => 'in-category']);
        $post->categories()->sync([$category->getKey()]);

        $this->makePost(['title' => 'Out of category', 'slug' => 'out-of-category']);

        $this->getJson('/api/v1/blog/posts?category=news')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'In category');
    }

    public function test_post_can_be_shown_by_slug(): void
    {
        $this->makePost(['title' => 'Find me', 'slug' => 'find-me']);

        $this->getJson('/api/v1/blog/posts/find-me')
            ->assertOk()
            ->assertJsonPath('data.title', 'Find me');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/blog/posts/not-found')->assertNotFound();
    }

    public function test_show_increments_views(): void
    {
        $post = $this->makePost(['title' => 'Counted', 'slug' => 'counted']);

        $this->getJson('/api/v1/blog/posts/counted')
            ->assertOk()
            ->assertJsonPath('data.views', 1);

        $this->assertDatabaseHas('posts', ['id' => $post->getKey(), 'views' => 1]);
    }

    public function test_categories_index_returns_categories(): void
    {
        $this->makeCategory(['name' => 'News', 'slug' => 'news']);

        $this->getJson('/api/v1/blog/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'News');
    }

    public function test_comment_can_be_submitted_as_guest(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/v1/blog/posts/{$post->getKey()}/comments", [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'content' => 'Nice article',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->getKey(),
            'content' => 'Nice article',
            'status' => CommentStatus::Pending->value,
        ]);
    }

    public function test_comment_requires_name_for_guests(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/v1/blog/posts/{$post->getKey()}/comments", [
            'content' => 'Anonymous',
        ])->assertJsonValidationErrors('name');
    }

    public function test_comments_index_returns_only_approved(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->create(['post_id' => $post->getKey()]);
        Comment::factory()->pending()->create(['post_id' => $post->getKey()]);

        $this->getJson("/api/v1/blog/posts/{$post->getKey()}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_comments_for_draft_post_return_404(): void
    {
        $post = Post::factory()->draft()->create();

        $this->getJson("/api/v1/blog/posts/{$post->getKey()}/comments")->assertNotFound();
    }
}
