<?php

namespace Modules\Network\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Models\User;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

/**
 * @property-read array{
 *     stats: array{websites: array<string, int>, users: array<string, int>},
 *     recent_websites: Collection<int, Website>,
 *     recent_users: Collection<int, User>
 * } $resource
 */
#[OA\Schema(
    schema: __CLASS__,
    required: ['stats', 'recent_websites', 'recent_users'],
    properties: [
        new OA\Property(
            property: 'stats',
            properties: [
                new OA\Property(
                    property: 'websites',
                    properties: [
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'active', type: 'integer'),
                        new OA\Property(property: 'inactive', type: 'integer'),
                        new OA\Property(property: 'suspended', type: 'integer'),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'users',
                    properties: [
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'verified', type: 'integer'),
                        new OA\Property(property: 'unverified', type: 'integer'),
                        new OA\Property(property: 'trashed', type: 'integer'),
                    ],
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'recent_websites',
            type: 'array',
            items: new OA\Items(type: WebsiteResource::class)
        ),
        new OA\Property(
            property: 'recent_users',
            type: 'array',
            items: new OA\Items(type: UserResource::class)
        ),
    ]
)]
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this->resource['stats'],
            'recent_websites' => WebsiteResource::collection($this->resource['recent_websites'])->resolve($request),
            'recent_users' => UserResource::collection($this->resource['recent_users'])->resolve($request),
        ];
    }
}
