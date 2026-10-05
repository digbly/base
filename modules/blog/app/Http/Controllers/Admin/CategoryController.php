<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Http\Controllers\Admin\Concerns\SyncsTranslations;
use Modules\Blog\Http\Requests\Admin\IndexCategoryRequest;
use Modules\Blog\Http\Requests\Admin\StoreCategoryRequest;
use Modules\Blog\Http\Requests\Admin\UpdateCategoryRequest;
use Modules\Blog\Http\Resources\CategoryResource;
use Modules\Blog\Models\Category;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    use SyncsTranslations;

    #[OA\Get(
        path: '/api/v1/admin/blog/categories',
        summary: 'List Blog Categories',
        operationId: 'admin.blog.categories.index',
        tags: ['Admin Blog Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
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
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $categories = Category::query()
            ->with('translations')
            ->withCount('posts')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->whereHas(
                    'translations',
                    fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                )
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 50));

        return CategoryResource::collection($categories);
    }

    #[OA\Get(
        path: '/api/v1/admin/blog/categories/{id}',
        summary: 'Show Blog Category',
        operationId: 'admin.blog.categories.show',
        tags: ['Admin Blog Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
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
    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category->load('translations')->loadCount('posts'));
    }

    #[OA\Post(
        path: '/api/v1/admin/blog/categories',
        summary: 'Create Blog Category',
        operationId: 'admin.blog.categories.store',
        tags: ['Admin Blog Categories'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: StoreCategoryRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Category created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CategoryResource::class)])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $data = $request->validated();

        $category = DB::transaction(function () use ($data): Category {
            $category = Category::create([
                'parent_id' => $data['parent_id'] ?? null,
                'is_home' => (bool) ($data['is_home'] ?? false),
            ]);

            $this->syncTranslations($category, $data['translations']);

            return $category;
        });

        return CategoryResource::make($category->load('translations')->loadCount('posts'));
    }

    #[OA\Put(
        path: '/api/v1/admin/blog/categories/{id}',
        summary: 'Update Blog Category',
        operationId: 'admin.blog.categories.update',
        tags: ['Admin Blog Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdateCategoryRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: CategoryResource::class)])
            ),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $category, $data): void {
            $category->update([
                'parent_id' => $request->has('parent_id')
                    ? $data['parent_id']
                    : $category->parent_id,
                'is_home' => $request->has('is_home')
                    ? (bool) $data['is_home']
                    : $category->is_home,
            ]);

            if (isset($data['translations'])) {
                $this->syncTranslations($category, $data['translations']);
            }
        });

        return CategoryResource::make($category->refresh()->load('translations')->loadCount('posts'));
    }

    #[OA\Delete(
        path: '/api/v1/admin/blog/categories/{id}',
        summary: 'Delete Blog Category',
        operationId: 'admin.blog.categories.destroy',
        tags: ['Admin Blog Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
