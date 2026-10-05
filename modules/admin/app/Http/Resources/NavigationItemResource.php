<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @property-read array{key: string, label: string, to: ?string, icon: string, permission: ?string, parent: ?string, priority: int, children: \Illuminate\Support\Collection} $resource
 */
#[OA\Schema(
    schema: __CLASS__,
    required: ['id', 'label', 'icon', 'children'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'dashboard'),
        new OA\Property(property: 'label', type: 'string', example: 'Dashboard'),
        new OA\Property(property: 'to', type: 'string', nullable: true, example: '/dashboard'),
        new OA\Property(property: 'icon', type: 'string', example: 'layout-dashboard'),
        new OA\Property(property: 'permission', type: 'string', nullable: true, example: 'dashboard.view'),
        new OA\Property(
            property: 'children',
            type: 'array',
            items: new OA\Items(type: self::class)
        ),
    ]
)]
class NavigationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['key'],
            'label' => $this->resource['label'],
            'to' => $this->resource['to'],
            'icon' => $this->resource['icon'],
            'permission' => $this->resource['permission'],
            'children' => self::collection($this->resource['children']),
        ];
    }
}
