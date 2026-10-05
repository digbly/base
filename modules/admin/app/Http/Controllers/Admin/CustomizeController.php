<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Actions\Customize\UpdateCustomize;
use Modules\Admin\Http\Requests\Admin\Customize\SettingRequest;
use Modules\Admin\Support\CustomizeCatalog;
use OpenApi\Attributes as OA;

class CustomizeController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/customize',
        summary: 'Customizer payload (panels, settings, pages and blocks)',
        operationId: 'customize.index',
        tags: ['Customize'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        responses: [
            new OA\Response(response: 200, description: 'Customizer payload'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json(['data' => app(CustomizeCatalog::class)->index()]);
    }

    #[OA\Post(
        path: '/api/v1/admin/customize',
        summary: 'Persist customizer settings, homepage blocks and widgets',
        operationId: 'customize.update',
        tags: ['Customize'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: SettingRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Customizer saved'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(SettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        app(UpdateCustomize::class)->handle(
            $data['setting'] ?? [],
            $data['theme_setting'] ?? [],
            $data['locale'] ?? app()->getLocale(),
            $request->has('blocks') ? (array) $request->input('blocks', []) : null,
            $request->has('widgets') ? (array) $request->input('widgets', []) : null,
        );

        return response()->json(['message' => __('admin.customize.notices.saved')]);
    }

    #[OA\Get(
        path: '/api/v1/admin/customize/page-blocks/{page}',
        summary: 'List the blocks assigned to a page grouped by container',
        operationId: 'customize.pageBlocks',
        tags: ['Customize'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Page blocks'),
            new OA\Response(response: 404, description: 'Page not found'),
        ]
    )]
    public function pageBlocks(string $page): JsonResponse
    {
        return response()->json(app(CustomizeCatalog::class)->pageBlocks($page));
    }

    #[OA\Get(
        path: '/api/v1/admin/customize/widgets',
        summary: 'List widgets, sidebars and their assignments for the customizer',
        operationId: 'customize.widgets',
        tags: ['Customize'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        responses: [
            new OA\Response(response: 200, description: 'Widget payload'),
        ]
    )]
    public function widgets(Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json(app(CustomizeCatalog::class)->widgets());
    }
}
