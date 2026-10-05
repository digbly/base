<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Auth\Models\User;
use Modules\Blog\Enums\CommentStatus;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use Tests\TestCase;

class AdminCommentControllerTest extends TestCase
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

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->base().'/comments')->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->base().'/comments')->assertForbidden();
    }

    public function test_index_filters_by_status(): void
    {
        Passport::actingAs($this->admin());

        $post = Post::factory()->create();
        Comment::factory()->count(2)->create(['post_id' => $post->getKey()]);
        Comment::factory()->pending()->create(['post_id' => $post->getKey()]);

        $this->getJson($this->base().'/comments?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson($this->base().'/comments')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_changes_status(): void
    {
        Passport::actingAs($this->admin());
        $comment = Comment::factory()->pending()->create();

        $this->putJson($this->base()."/comments/{$comment->getKey()}", [
            'status' => CommentStatus::Approved->value,
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->getKey(),
            'status' => 'approved',
        ]);
    }

    public function test_update_validates_status(): void
    {
        Passport::actingAs($this->admin());
        $comment = Comment::factory()->pending()->create();

        $this->putJson($this->base()."/comments/{$comment->getKey()}", [
            'status' => 'unknown',
        ])->assertJsonValidationErrors('status');
    }

    public function test_destroy_deletes_comment(): void
    {
        Passport::actingAs($this->admin());
        $comment = Comment::factory()->create();

        $this->deleteJson($this->base()."/comments/{$comment->getKey()}")
            ->assertOk();

        $this->assertDatabaseMissing('comments', ['id' => $comment->getKey()]);
    }
}
