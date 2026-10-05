<?php

namespace Modules\Network\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Models\User;
use Modules\Network\Http\Requests\IndexUserRequest;
use Modules\Network\Http\Requests\ResetUserPasswordRequest;
use Modules\Network\Http\Requests\StoreUserRequest;
use Modules\Network\Http\Requests\UpdateUserRequest;
use Modules\Network\Http\Resources\RoleResource;
use Modules\Network\Http\Resources\UserResource;

/**
 * Inertia-facing network-wide user management.
 *
 * Mirrors {@see \Modules\Network\Http\Controllers\UserController} (the JSON
 * API) but returns Inertia pages and redirects instead of resources.
 */
class UserController extends Controller
{
    public function index(IndexUserRequest $request): Response
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
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        return Inertia::render('Network::users/Index', [
            'title' => __('network.networkAdmin.users.title'),
            'users' => UserResource::collection($users),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'role' => $filters['role'] ?? null,
                'trashed' => $filters['trashed'] ?? null,
            ],
            'roles' => RoleResource::collection($this->roleOptions()),
            'selfId' => $request->user()->getKey(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Network::users/Form', [
            'title' => __('network.networkAdmin.userForm.createTitle'),
            'user' => null,
            'roles' => RoleResource::collection($this->roleOptions()),
            'selfId' => $request->user()->getKey(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_super_admin' => (bool) ($data['is_super_admin'] ?? false),
            ]);

            $user->syncRoles($data['roles'] ?? []);
        });

        return redirect()
            ->route('admin.network.users.index')
            ->with('success', __('network.networkAdmin.notices.userCreated'));
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('Network::users/Form', [
            'title' => __('network.networkAdmin.userForm.editTitle'),
            'user' => UserResource::make($user->load($this->resourceRelations()))->resolve(),
            'roles' => RoleResource::collection($this->roleOptions()),
            'selfId' => $request->user()->getKey(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->is($user)
            && $request->has('is_super_admin')
            && ! (bool) $data['is_super_admin']
        ) {
            throw ValidationException::withMessages([
                'is_super_admin' => __('network.networkAdmin.errors.selfSuperAdmin'),
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

        return redirect()
            ->route('admin.network.users.index')
            ->with('success', __('network.networkAdmin.notices.userUpdated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => __('network.networkAdmin.errors.selfDelete'),
            ]);
        }

        $user->delete();

        return back()->with('success', __('network.networkAdmin.notices.userDeleted'));
    }

    public function restore(User $user): RedirectResponse
    {
        if ($user->trashed()) {
            $user->restore();
        }

        return back()->with('success', __('network.networkAdmin.notices.userRestored'));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['password' => $request->validated('password')])->save();

        return back()->with('success', __('network.networkAdmin.notices.passwordReset'));
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('network.networkAdmin.errors.alreadyVerified'),
            ]);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', __('network.networkAdmin.notices.verificationSent'));
    }

    /**
     * @return Collection<int, Role>
     */
    protected function roleOptions(): Collection
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->unique('name')
            ->values();
    }

    /**
     * @return list<string>
     */
    protected function resourceRelations(): array
    {
        return ['roles', 'permissions', 'roles.permissions'];
    }
}
