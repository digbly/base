<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Requests\Admin\IndexUserRequest;
use Modules\Admin\Http\Requests\Admin\ResetUserPasswordRequest;
use Modules\Admin\Http\Requests\Admin\StoreUserRequest;
use Modules\Admin\Http\Requests\Admin\UpdateUserRequest;
use Modules\Admin\Http\Resources\MessageResource;
use Modules\Admin\Http\Resources\UserResource;
use Modules\Auth\Enums\Permission;
use Modules\Auth\Models\User;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/users',
        summary: 'List Users',
        operationId: 'admin.users.index',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'trashed', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['only', 'with'])),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['name', 'email', 'created_at'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Users list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: UserResource::class)
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $trashed = $filters['trashed'] ?? null;

        $users = User::query()
            ->with($this->resourceRelations())
            ->when($trashed === 'only', fn ($query) => $query->onlyTrashed())
            ->when($trashed === 'with', fn ($query) => $query->withTrashed())
            ->when(
                $filters['search'] ?? null,
                function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $filters['role'] ?? null,
                fn ($query, string $role) => $query->whereHas(
                    'roles',
                    fn ($query) => $query->where('name', $role)
                )
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return UserResource::collection($users);
    }

    #[OA\Get(
        path: '/api/v1/admin/users/{id}',
        summary: 'Show User',
        operationId: 'admin.users.show',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(User $user): UserResource
    {
        return UserResource::make($user->load($this->resourceRelations()));
    }

    #[OA\Post(
        path: '/api/v1/admin/users',
        summary: 'Create User',
        operationId: 'admin.users.store',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: StoreUserRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUserRequest $request): UserResource
    {
        $data = $request->validated();

        if (($data['is_super_admin'] ?? false) && ! $request->user('api')?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'is_super_admin' => ['Only super admins can grant super admin access.'],
            ]);
        }

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_super_admin' => (bool) ($data['is_super_admin'] ?? false),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });

        return UserResource::make($user->load($this->resourceRelations()));
    }

    #[OA\Put(
        path: '/api/v1/admin/users/{id}',
        summary: 'Update User',
        operationId: 'admin.users.update',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdateUserRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->ensureCanManage($request, $user);

        $data = $request->validated();
        $actor = $request->user('api');

        if ($request->has('is_super_admin')
            && (bool) $data['is_super_admin'] !== $user->isSuperAdmin()
            && ! $actor?->isSuperAdmin()
        ) {
            throw ValidationException::withMessages([
                'is_super_admin' => ['Only super admins can change super admin access.'],
            ]);
        }

        DB::transaction(function () use ($request, $user, $data, $actor): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if ($request->has('roles')) {
                $user->syncRoles($data['roles'] ?? []);
            }

            if ($request->has('is_super_admin')) {
                $user->is_super_admin = (bool) $data['is_super_admin'];
                $user->save();
            }

            if ($actor?->getKey() === $user->getKey()) {
                $fresh = $user->fresh();

                $canManageUsers = $fresh->isSuperAdmin()
                    || $fresh->getAllPermissions()->contains('name', Permission::UsersManage->value);

                if (! $canManageUsers) {
                    throw ValidationException::withMessages([
                        'roles' => ['You cannot remove your own ability to manage users.'],
                    ]);
                }
            }
        });

        return UserResource::make($user->load($this->resourceRelations()));
    }

    #[OA\Delete(
        path: '/api/v1/admin/users/{id}',
        summary: 'Delete User',
        operationId: 'admin.users.destroy',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Cannot delete yourself'),
        ]
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManage($request, $user);

        if ($request->user('api')?->getKey() === $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    #[OA\Post(
        path: '/api/v1/admin/users/{id}/restore',
        summary: 'Restore User',
        operationId: 'admin.users.restore',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User restored',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: UserResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function restore(User $user): UserResource
    {
        if ($user->trashed()) {
            $user->restore();
        }

        return UserResource::make($user->refresh()->load($this->resourceRelations()));
    }

    #[OA\Put(
        path: '/api/v1/admin/users/{id}/password',
        summary: 'Reset User Password',
        operationId: 'admin.users.reset-password',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: ResetUserPasswordRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resetPassword(ResetUserPasswordRequest $request, User $user): MessageResource
    {
        $this->ensureCanManage($request, $user);

        $user->forceFill(['password' => $request->validated('password')])->save();

        return MessageResource::make('Password reset successfully!');
    }

    #[OA\Post(
        path: '/api/v1/admin/users/{id}/resend-verification',
        summary: 'Resend Verification Email',
        operationId: 'admin.users.resend-verification',
        tags: ['Admin Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verification link sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: MessageResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Email already verified'),
        ]
    )]
    public function resendVerification(User $user): MessageResource
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['This user has already verified their email.'],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return MessageResource::make('Verification link sent!');
    }

    /**
     * Relations required by UserResource without triggering lazy loads.
     *
     * @return list<string>
     */
    protected function resourceRelations(): array
    {
        return ['roles', 'permissions', 'roles.permissions'];
    }

    /**
     * A regular admin must not be able to modify (and thereby take over) a
     * super admin account.
     */
    protected function ensureCanManage(Request $request, User $target): void
    {
        if ($target->isSuperAdmin() && ! $request->user('api')?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot manage a super admin account.'],
            ]);
        }
    }
}
