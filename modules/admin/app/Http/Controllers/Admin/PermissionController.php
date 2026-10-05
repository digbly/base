<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Admin\Http\Resources\PermissionResource;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/permissions',
        summary: 'List Permissions',
        operationId: 'admin.permissions.index',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permissions list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: PermissionResource::class)
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        return PermissionResource::collection($permissions);
    }
}
