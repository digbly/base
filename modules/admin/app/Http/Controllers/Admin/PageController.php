<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Pages\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admin\Http\Requests\Admin\IndexPageRequest;
use Modules\Admin\Http\Requests\Admin\PageRequest;
use Modules\Admin\Http\Resources\PageResource;
use OpenApi\Attributes as OA;

class PageController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/pages',
        summary: 'List pages',
        operationId: 'pages.index',
        tags: ['Pages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['published', 'draft'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Page collection'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexPageRequest $request): AnonymousResourceCollection
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        $filters = $request->validated();

        $pages = Page::query()
            ->with('translations')
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->whereHas(
                    'translations',
                    fn ($query) => $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));

        return PageResource::collection($pages);
    }

    #[OA\Post(
        path: '/api/v1/admin/pages',
        summary: 'Create a page',
        operationId: 'pages.store',
        tags: ['Pages'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: PageRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Page created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(PageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $locale = $data['locale'] ?? app()->getLocale();

        $page = Page::create([
            'status' => $data['status'] ?? PageStatus::Published->value,
            'template' => $data['template'] ?? null,
        ]);

        $page->fillTranslation($locale, $data);

        return response()->json([
            'data' => PageResource::make($page->fresh('translations'))->resolve(),
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/admin/pages/{page}',
        summary: 'Show a page',
        operationId: 'pages.show',
        tags: ['Pages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Page'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Page $page): JsonResponse
    {
        return response()->json([
            'data' => PageResource::make($page->load('translations'))->resolve(),
        ]);
    }

    #[OA\Put(
        path: '/api/v1/admin/pages/{page}',
        summary: 'Update a page',
        operationId: 'pages.update',
        tags: ['Pages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: PageRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Page updated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(PageRequest $request, Page $page): JsonResponse
    {
        $data = $request->validated();
        $locale = $data['locale'] ?? app()->getLocale();

        $page->status = $data['status'] ?? $page->status->value;

        if (array_key_exists('template', $data)) {
            $page->template = $data['template'];
        }

        $page->save();

        $page->fillTranslation($locale, $data);

        return response()->json([
            'data' => PageResource::make($page->fresh('translations'))->resolve(),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/admin/pages/{page}',
        summary: 'Delete a page',
        operationId: 'pages.destroy',
        tags: ['Pages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Page deleted'),
        ]
    )]
    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return response()->json(['message' => __('admin.pages.notices.deleted')]);
    }
}
