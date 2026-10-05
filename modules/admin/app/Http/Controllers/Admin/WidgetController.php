<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Facades\Sidebar;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Actions\Widget\UpdateSidebarWidgets;
use Modules\Admin\Http\Requests\Admin\WidgetUpdateRequest;
use Modules\Admin\Support\WidgetCatalog;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

class WidgetController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/widgets',
        summary: 'List available widgets, sidebars and the widgets attached to each sidebar',
        operationId: 'widgets.index',
        tags: ['Widgets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Widget index payload'),
        ]
    )]
    public function index(Website $website, Request $request): JsonResponse
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return response()->json(['data' => app(WidgetCatalog::class)->payload()]);
    }

    #[OA\Put(
        path: '/api/v1/admin/websites/{website}/widgets/{sidebar}',
        summary: 'Sync the widgets attached to a sidebar',
        operationId: 'widgets.update',
        tags: ['Widgets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sidebar', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: WidgetUpdateRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sidebar saved'),
            new OA\Response(response: 404, description: 'Sidebar not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Website $website, WidgetUpdateRequest $request, string $sidebar): JsonResponse
    {
        abort_if(Sidebar::get($sidebar) === null, 404);

        app(UpdateSidebarWidgets::class)->handle(
            $sidebar,
            $request->input('content', []),
            $request->validated('locale') ?? app()->getLocale(),
            theme_name(),
        );

        return response()->json([
            'message' => __('admin.widgets.notices.saved'),
        ]);
    }
}
