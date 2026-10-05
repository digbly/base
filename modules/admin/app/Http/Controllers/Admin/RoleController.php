<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\Admin\StoreRoleRequest;
use Modules\Admin\Http\Requests\Admin\UpdateRoleRequest;
use Modules\Admin\Http\Resources\RoleResource;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/roles',
        summary: 'List Roles',
        operationId: 'admin.roles.index',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Roles list',
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
            ->get();

        return RoleResource::collection($roles);
    }

    #[OA\Get(
        path: '/api/v1/admin/roles/{id}',
        summary: 'Show Role',
        operationId: 'admin.roles.show',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: RoleResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role->load('permissions'));
    }

    #[OA\Post(
        path: '/api/v1/admin/roles',
        summary: 'Create Role',
        operationId: 'admin.roles.store',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: StoreRoleRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Role created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: RoleResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreRoleRequest $request): RoleResource
    {
        $data = $request->validated();

        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => config('auth.defaults.guard'),
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return RoleResource::make($role->load('permissions'));
    }

    #[OA\Put(
        path: '/api/v1/admin/roles/{id}',
        summary: 'Update Role',
        operationId: 'admin.roles.update',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdateRoleRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: RoleResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        $role->update(['name' => $data['name']]);

        if ($request->has('permissions')) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return RoleResource::make($role->load('permissions'));
    }

    #[OA\Delete(
        path: '/api/v1/admin/roles/{id}',
        summary: 'Delete Role',
        operationId: 'admin.roles.destroy',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Role is assigned to users'),
        ]
    )]
    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Cannot delete a role that is assigned to users.'],
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
