<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menus\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admin\Actions\Menu\UpdateMenu;
use Modules\Admin\Http\Requests\Admin\MenuRequest;
use Modules\Admin\Http\Resources\MenuResource;
use Modules\Admin\Support\MenuCatalog;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

class MenuController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/menus',
        summary: 'List Menus',
        operationId: 'menus.index',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menus list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: MenuResource::class)
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Website $website, Request $request): AnonymousResourceCollection
    {
        $menus = Menu::withDataItems()
            ->paginate($request->integer('per_page', 15));

        return MenuResource::collection($menus);
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/menus/{id}',
        summary: 'Show Menu',
        operationId: 'menus.show',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MenuResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Menu not found'),
        ]
    )]
    public function show(Website $website, Menu $menu): MenuResource
    {
        return MenuResource::make(
            Menu::withDataItems()->findOrFail($menu->getKey())
        );
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/menus/boxes',
        summary: 'List available menu boxes',
        operationId: 'menus.boxes',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu boxes'),
        ]
    )]
    public function boxes(Website $website, Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json(['data' => app(MenuCatalog::class)->boxes()]);
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/menus/boxes/{box}',
        summary: 'List items available for a menu box',
        operationId: 'menus.boxes.items',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'box', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu box items'),
        ]
    )]
    public function boxItems(Website $website, string $box, Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json([
            'results' => app(MenuCatalog::class)->boxItems($box, $request->string('q')->toString()),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/menus/locations',
        summary: 'List menu locations',
        operationId: 'menus.locations',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu locations'),
        ]
    )]
    public function locations(Website $website, Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json(app(MenuCatalog::class)->locations());
    }

    #[OA\Post(
        path: '/api/v1/admin/websites/{website}/menus',
        summary: 'Create Menu',
        operationId: 'menus.store',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: MenuRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Menu created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MenuResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Website $website, MenuRequest $request): MenuResource
    {
        $menu = Menu::create([
            'name' => $request->validated('name'),
            'website_id' => website_id(),
        ]);

        return MenuResource::make($menu);
    }

    #[OA\Put(
        path: '/api/v1/admin/websites/{website}/menus/{id}',
        summary: 'Update Menu',
        operationId: 'menus.update',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: MenuRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MenuResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Menu not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Website $website, MenuRequest $request, Menu $menu): MenuResource
    {
        app(UpdateMenu::class)->handle(
            $menu,
            $request->validated('name'),
            json_decode($request->validated('content'), true, 512, JSON_THROW_ON_ERROR),
            $request->validated('locale') ?? app()->getLocale(),
            $request->has('location') ? (array) $request->input('location', []) : null,
        );

        return MenuResource::make(
            Menu::withDataItems()->findOrFail($menu->getKey())
        );
    }

    #[OA\Delete(
        path: '/api/v1/admin/websites/{website}/menus/{id}',
        summary: 'Delete Menu',
        operationId: 'menus.destroy',
        tags: ['Menus'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu deleted'),
            new OA\Response(response: 404, description: 'Menu not found'),
        ]
    )]
    public function destroy(Website $website, Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json(['message' => 'Menu deleted successfully.']);
    }
}
