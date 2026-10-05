<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Blog\Http\Resources\CategoryResource;
use Modules\Blog\Models\Category;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/blog/categories',
        summary: 'List blog categories',
        operationId: 'blog.categories.index',
        tags: ['Blog'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categories list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: CategoryResource::class)),
                    ]
                )
            ),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->with('translations')
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->orderBy('created_at')
            ->get();

        return CategoryResource::collection($categories);
    }

    #[OA\Get(
        path: '/api/v1/blog/categories/{slug}',
        summary: 'Show a blog category',
        operationId: 'blog.categories.show',
        tags: ['Blog'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category detail',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CategoryResource::class)])
            ),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function show(string $slug): CategoryResource|JsonResponse
    {
        $category = Category::query()
            ->with('translations')
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->whereHas('translations', fn (Builder $query) => $query->where('slug', $slug))
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        return CategoryResource::make($category);
    }
}
