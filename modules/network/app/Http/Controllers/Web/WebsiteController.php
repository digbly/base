<?php

namespace Modules\Network\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Models\User;
use Modules\Network\Http\Requests\IndexWebsiteRequest;
use Modules\Network\Http\Requests\WebsiteRequest;
use Modules\Network\Http\Resources\UserResource;
use Modules\Network\Http\Resources\WebsiteResource;
use Modules\Network\Models\Database;
use Modules\Network\Models\Website;

/**
 * Inertia-facing network-wide website management.
 *
 * Mirrors {@see \Modules\Network\Http\Controllers\WebsiteController} (the JSON
 * API) but returns Inertia pages and redirects instead of resources.
 */
class WebsiteController extends Controller
{
    private const OWNER_LIMIT = 100;

    public function index(IndexWebsiteRequest $request): Response
    {
        $filters = $request->validated();

        $websites = Website::query()
            ->with('owner')
            ->withCount('users')
            ->when(
                $filters['q'] ?? null,
                fn (Builder $query, string $q) => $query->where(function (Builder $query) use ($q): void {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('domain', 'like', "%{$q}%")
                        ->orWhere('subdomain', 'like', "%{$q}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        return Inertia::render('Network::websites/Index', [
            'title' => __('network.networkAdmin.websites.title'),
            'websites' => WebsiteResource::collection($websites),
            'filters' => [
                'q' => $filters['q'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'owners' => UserResource::collection($this->ownerOptions()),
            'networkDomain' => config('network.domain'),
        ]);
    }

    public function store(WebsiteRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $website = Website::create($request->validated());

            $website->users()->syncWithoutDetaching([$website->user_id]);

            if ($website->database) {
                Database::query()->where('name', $website->database)->increment('total_websites');
            }
        });

        return back()->with('success', __('network.networkAdmin.notices.websiteCreated'));
    }

    public function update(WebsiteRequest $request, Website $website): RedirectResponse
    {
        $oldDatabase = $website->database;

        DB::transaction(function () use ($request, $website, $oldDatabase): void {
            $website->update($request->validated());

            $website->users()->syncWithoutDetaching([$website->user_id]);

            if ($oldDatabase !== $website->database) {
                if ($oldDatabase) {
                    Database::query()->where('name', $oldDatabase)->decrement('total_websites');
                }

                if ($website->database) {
                    Database::query()->where('name', $website->database)->increment('total_websites');
                }
            }
        });

        return back()->with('success', __('network.networkAdmin.notices.websiteUpdated'));
    }

    public function destroy(Website $website): RedirectResponse
    {
        DB::transaction(function () use ($website): void {
            if ($website->database) {
                Database::query()->where('name', $website->database)->decrement('total_websites');
            }

            $website->delete();
        });

        return back()->with('success', __('network.networkAdmin.notices.websiteDeleted'));
    }

    /**
     * @return Collection<int, User>
     */
    protected function ownerOptions(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->limit(self::OWNER_LIMIT)
            ->get();
    }
}
