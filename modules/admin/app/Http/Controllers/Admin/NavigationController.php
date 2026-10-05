<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Facades\Menu;
use App\Http\Controllers\Controller;
use App\Support\MenuRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admin\Http\Resources\NavigationItemResource;
use OpenApi\Attributes as OA;

class NavigationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/navigation',
        summary: 'Admin sidebar navigation',
        operationId: 'navigation.index',
        tags: ['Navigation'],
        security: [['bearerAuth' => []]],
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
    public function index(Request $request): AnonymousResourceCollection
    {
        app()->setLocale($request->getPreferredLanguage(['en', 'vi']));

        return NavigationItemResource::collection(
            Menu::tree(MenuRepository::POSITION_ADMIN)
        );
    }
}
