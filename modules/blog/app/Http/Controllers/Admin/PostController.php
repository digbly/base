<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Http\Controllers\Admin\Concerns\SyncsTranslations;
use Modules\Blog\Http\Requests\Admin\IndexPostRequest;
use Modules\Blog\Http\Requests\Admin\StorePostRequest;
use Modules\Blog\Http\Requests\Admin\UpdatePostRequest;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Models\Post;
use OpenApi\Attributes as OA;

class PostController extends Controller
{
    use SyncsTranslations;

    #[OA\Get(
        path: '/api/v1/admin/blog/posts',
        summary: 'List Blog Posts',
        operationId: 'admin.blog.posts.index',
        tags: ['Admin Blog Posts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'published'])),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at', 'views'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Posts list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: PostResource::class)),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexPostRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $posts = Post::query()
            ->with($this->resourceRelations())
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->whereHas(
                    'translations',
                    fn (Builder $query) => $query->where('title', 'like', "%{$search}%")
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['category'] ?? null,
                fn (Builder $query, string $category) => $query->whereHas(
                    'categories',
                    fn (Builder $query) => $query->where('post_categories.id', $category)
                )
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return PostResource::collection($posts);
    }

    #[OA\Get(
        path: '/api/v1/admin/blog/posts/{id}',
        summary: 'Show Blog Post',
        operationId: 'admin.blog.posts.show',
        tags: ['Admin Blog Posts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post detail',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: PostResource::class)])
            ),
            new OA\Response(response: 404, description: 'Post not found'),
        ]
    )]
    public function show(Post $post): PostResource
    {
        return PostResource::make($post->load($this->resourceRelations()));
    }

    #[OA\Post(
        path: '/api/v1/admin/blog/posts',
        summary: 'Create Blog Post',
        operationId: 'admin.blog.posts.store',
        tags: ['Admin Blog Posts'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: StorePostRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Post created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: PostResource::class)])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePostRequest $request): PostResource
    {
        $data = $request->validated();

        $post = DB::transaction(function () use ($request, $data): Post {
            $post = Post::create([
                'status' => $data['status'],
                'user_id' => $data['user_id'] ?? $request->user('api')?->getKey(),
            ]);

            $this->syncTranslations($post, $data['translations']);
            $post->categories()->sync($data['categories'] ?? []);

            return $post;
        });

        return PostResource::make($post->load($this->resourceRelations()));
    }

    #[OA\Put(
        path: '/api/v1/admin/blog/posts/{id}',
        summary: 'Update Blog Post',
        operationId: 'admin.blog.posts.update',
        tags: ['Admin Blog Posts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdatePostRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: PostResource::class)])
            ),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $post, $data): void {
            $post->update([
                'status' => $data['status'] ?? $post->status->value,
                'user_id' => $request->has('user_id')
                    ? $data['user_id']
                    : $post->user_id,
            ]);

            if (isset($data['translations'])) {
                $this->syncTranslations($post, $data['translations']);
            }

            if ($request->has('categories')) {
                $post->categories()->sync($data['categories'] ?? []);
            }
        });

        return PostResource::make($post->refresh()->load($this->resourceRelations()));
    }

    #[OA\Delete(
        path: '/api/v1/admin/blog/posts/{id}',
        summary: 'Delete Blog Post',
        operationId: 'admin.blog.posts.destroy',
        tags: ['Admin Blog Posts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post deleted'),
            new OA\Response(response: 404, description: 'Post not found'),
        ]
    )]
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.']);
    }

    /**
     * @return list<string>
     */
    protected function resourceRelations(): array
    {
        return ['translations', 'categories.translations', 'author'];
    }
}
