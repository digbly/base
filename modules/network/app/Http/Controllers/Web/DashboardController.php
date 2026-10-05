<?php

namespace Modules\Network\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Http\Resources\DashboardResource;
use Modules\Network\Models\Website;

/**
 * Inertia-facing network overview.
 *
 * The aggregation mirrors {@see \Modules\Network\Http\Controllers\DashboardController}
 * (the JSON API); extracting it into a shared action is planned.
 */
class DashboardController extends Controller
{
    private const RECENT_LIMIT = 5;

    public function index(): Response
    {
        $recentWebsites = Website::query()
            ->with('owner')
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $recentUsers = User::query()
            ->with(['roles', 'permissions', 'roles.permissions'])
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $websiteCounts = Website::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $userTotal = User::query()->count();
        $userVerified = User::query()->whereNotNull('email_verified_at')->count();

        return Inertia::render('Network::dashboard/Index', [
            'title' => __('network.networkAdmin.dashboard.title'),
            'dashboard' => DashboardResource::make([
                'stats' => [
                    'websites' => [
                        'total' => (int) $websiteCounts->sum(),
                        'active' => (int) ($websiteCounts[WebsiteStatus::ACTIVE->value] ?? 0),
                        'inactive' => (int) ($websiteCounts[WebsiteStatus::INACTIVE->value] ?? 0),
                        'suspended' => (int) ($websiteCounts[WebsiteStatus::SUSPENDED->value] ?? 0),
                    ],
                    'users' => [
                        'total' => $userTotal,
                        'verified' => $userVerified,
                        'unverified' => $userTotal - $userVerified,
                        'trashed' => User::query()->onlyTrashed()->count(),
                    ],
                ],
                'recent_websites' => $recentWebsites,
                'recent_users' => $recentUsers,
            ])->resolve(),
        ]);
    }
}
