<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Http\Resources\DashboardResource;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

/**
 * Read-only aggregate used by the network admin dashboard.
 */
class DashboardController extends Controller
{
    private const RECENT_LIMIT = 5;

    #[OA\Get(
        path: '/api/v1/network/dashboard',
        summary: 'Get the network dashboard overview',
        operationId: 'network.dashboard',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Network statistics and recent activity',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: DashboardResource::class),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): DashboardResource
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

        return DashboardResource::make([
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
        ]);
    }
}
