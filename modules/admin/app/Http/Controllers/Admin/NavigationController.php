<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Facades\Menu;
use App\Http\Controllers\Controller;
use App\Support\MenuRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admin\Http\Resources\NavigationItemResource;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

class NavigationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/navigation',
        summary: 'Admin sidebar navigation',
        operationId: 'navigation.index',
        tags: ['Navigation'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Navigation tree',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: NavigationItemResource::class)
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Website $website, Request $request): AnonymousResourceCollection
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return NavigationItemResource::collection(
            Menu::tree(MenuRepository::POSITION_ADMIN)
        );
    }
}
