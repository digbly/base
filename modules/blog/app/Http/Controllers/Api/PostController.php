<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Blog\Http\Requests\Api\IndexPostRequest;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Models\Post;
use OpenApi\Attributes as OA;

class PostController extends Controller
{
    #[OA\Get(
        path: '/api/v1/blog/posts',
        summary: 'List published blog posts',
        operationId: 'blog.posts.index',
        tags: ['Blog'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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
        ]
    )]
    public function index(IndexPostRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $posts = Post::query()
            ->published()
            ->with($this->resourceRelations())
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->whereHas(
                    'translations',
                    fn (Builder $query) => $query->where('title', 'like', "%{$search}%")
                )
            )
            ->when(
                $filters['category'] ?? null,
                fn (Builder $query, string $category) => $query->whereHas(
                    'categories.translations',
                    fn (Builder $query) => $query->where('slug', $category)
                )
            )
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? config('blog.per_page', 15)));

        return PostResource::collection($posts);
    }

    #[OA\Get(
        path: '/api/v1/blog/posts/{slug}',
        summary: 'Show a published blog post',
        operationId: 'blog.posts.show',
        tags: ['Blog'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
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
    public function show(string $slug): PostResource
    {
        $post = Post::query()
            ->published()
            ->with($this->resourceRelations())
            ->whereHas('translations', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();

        $post->newQuery()->whereKey($post->getKey())->increment('views');
        $post->views++;

        return PostResource::make($post);
    }

    /**
     * @return list<string>
     */
    protected function resourceRelations(): array
    {
        return ['translations', 'categories.translations', 'author'];
    }
}
