<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Blog\Http\Requests\Admin\IndexCommentRequest;
use Modules\Blog\Http\Requests\Admin\UpdateCommentRequest;
use Modules\Blog\Http\Resources\CommentResource;
use Modules\Blog\Models\Comment;
use OpenApi\Attributes as OA;

class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/blog/comments',
        summary: 'List Blog Comments',
        operationId: 'admin.blog.comments.index',
        tags: ['Admin Blog Comments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'spam', 'rejected'])),
            new OA\Parameter(name: 'post_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
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
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexCommentRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $comments = Comment::query()
            ->with('author')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $query) => $query->where('content', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['post_id'] ?? null,
                fn (Builder $query, string $postId) => $query->where('post_id', $postId)
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return CommentResource::collection($comments);
    }

    #[OA\Get(
        path: '/api/v1/admin/blog/comments/{id}',
        summary: 'Show Blog Comment',
        operationId: 'admin.blog.comments.show',
        tags: ['Admin Blog Comments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment detail',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CommentResource::class)])
            ),
            new OA\Response(response: 404, description: 'Comment not found'),
        ]
    )]
    public function show(Comment $comment): CommentResource
    {
        return CommentResource::make($comment->load('author'));
    }

    #[OA\Put(
        path: '/api/v1/admin/blog/comments/{id}',
        summary: 'Update Blog Comment Status',
        operationId: 'admin.blog.comments.update',
        tags: ['Admin Blog Comments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdateCommentRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CommentResource::class)])
            ),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateCommentRequest $request, Comment $comment): CommentResource
    {
        $comment->update(['status' => $request->validated('status')]);

        return CommentResource::make($comment->refresh()->load('author'));
    }

    #[OA\Delete(
        path: '/api/v1/admin/blog/comments/{id}',
        summary: 'Delete Blog Comment',
        operationId: 'admin.blog.comments.destroy',
        tags: ['Admin Blog Comments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comment deleted'),
            new OA\Response(response: 404, description: 'Comment not found'),
        ]
    )]
    public function destroy(Comment $comment): JsonResponse
    {
        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully.']);
    }
}
