<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Network\Http\Resources\RoleResource;
use OpenApi\Attributes as OA;

/**
 * Exposes the role catalog available for network-wide user management.
 */
class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/v1/network/roles',
        summary: 'List the role catalog',
        operationId: 'network.roles.index',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: RoleResource::class)
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->unique('name')
            ->values();

        return RoleResource::collection($roles);
    }
}
