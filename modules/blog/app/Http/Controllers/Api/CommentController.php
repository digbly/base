<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Enums\CommentStatus;
use Modules\Blog\Enums\PostStatus;
use Modules\Blog\Http\Requests\Api\StoreCommentRequest;
use Modules\Blog\Http\Resources\CommentResource;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;
use OpenApi\Attributes as OA;

class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/blog/posts/{post}/comments',
        summary: 'List approved comments of a post',
        operationId: 'blog.comments.index',
        tags: ['Blog'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comments list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: CommentResource::class)),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Post not found'),
        ]
    )]
    public function index(Post $post): AnonymousResourceCollection
    {
        abort_unless($post->status === PostStatus::Published, 404);

        $comments = $post->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with(['replies' => fn ($query) => $query->approved()])
            ->latest()
            ->paginate(config('blog.per_page', 15));

        return CommentResource::collection($comments);
    }

    #[OA\Post(
        path: '/api/v1/blog/posts/{post}/comments',
        summary: 'Create a comment',
        operationId: 'blog.comments.store',
        tags: ['Blog'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: StoreCommentRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comment created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CommentResource::class)])
            ),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCommentRequest $request, Post $post): CommentResource
    {
        abort_unless($post->status === PostStatus::Published, 404);

        $data = $request->validated();
        $user = $request->user('api');

        if (! $user && empty($data['name'])) {
            throw ValidationException::withMessages([
                'name' => ['The name field is required.'],
            ]);
        }

        if ($request->filled('parent_id')) {
            $belongsToPost = Comment::query()
                ->where('post_id', $post->getKey())
                ->whereKey($data['parent_id'])
                ->exists();

            if (! $belongsToPost) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The selected parent comment is invalid.'],
                ]);
            }
        }

        $comment = Comment::create([
            'post_id' => $post->getKey(),
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => $user?->getKey(),
            'name' => $data['name'] ?? $user?->name,
            'email' => $data['email'] ?? $user?->email,
            'content' => $data['content'],
            'status' => CommentStatus::Pending,
        ]);

        return CommentResource::make($comment->load('author'));
    }
}
