<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\User;
use Modules\Network\Http\Requests\IndexUserRequest;
use Modules\Network\Http\Requests\ResetUserPasswordRequest;
use Modules\Network\Http\Requests\StoreUserRequest;
use Modules\Network\Http\Requests\UpdateUserRequest;
use Modules\Network\Http\Resources\MessageResource;
use Modules\Network\Http\Resources\UserResource;
use OpenApi\Attributes as OA;

/**
 * Network-wide user management. These endpoints are not scoped to a single
 * website and require super admin access.
 */
class UserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/network/users',
        summary: 'List every user in the network',
        operationId: 'network.users.index',
        tags: ['Network'],
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
                description: 'Paginated users',
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
        path: '/api/v1/network/users/{id}',
        summary: 'Show a user',
        operationId: 'network.users.show',
        tags: ['Network'],
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
        path: '/api/v1/network/users',
        summary: 'Create a user',
        operationId: 'network.users.store',
        tags: ['Network'],
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
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreUserRequest $request): UserResource
    {
        $data = $request->validated();

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
        path: '/api/v1/network/users/{id}',
        summary: 'Update a user',
        operationId: 'network.users.update',
        tags: ['Network'],
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
        $data = $request->validated();
        $actor = $request->user('api');

        if ($actor?->getKey() === $user->getKey()
            && $request->has('is_super_admin')
            && ! (bool) $data['is_super_admin']
        ) {
            throw ValidationException::withMessages([
                'is_super_admin' => ['You cannot remove your own super admin access.'],
            ]);
        }

        DB::transaction(function () use ($request, $user, $data): void {
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
        });

        return UserResource::make($user->load($this->resourceRelations()));
    }

    #[OA\Delete(
        path: '/api/v1/network/users/{id}',
        summary: 'Delete a user',
        operationId: 'network.users.destroy',
        tags: ['Network'],
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
        if ($request->user('api')?->getKey() === $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    #[OA\Post(
        path: '/api/v1/network/users/{id}/restore',
        summary: 'Restore a user',
        operationId: 'network.users.restore',
        tags: ['Network'],
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
        path: '/api/v1/network/users/{id}/password',
        summary: 'Reset a user password',
        operationId: 'network.users.reset-password',
        tags: ['Network'],
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
        $user->forceFill(['password' => $request->validated('password')])->save();

        return MessageResource::make('Password reset successfully!');
    }

    #[OA\Post(
        path: '/api/v1/network/users/{id}/resend-verification',
        summary: 'Resend the verification email',
        operationId: 'network.users.resend-verification',
        tags: ['Network'],
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
}
