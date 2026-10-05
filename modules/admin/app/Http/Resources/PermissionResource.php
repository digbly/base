<?php

namespace Modules\Admin\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @property-read Permission $resource
 */
#[OA\Schema(
    schema: __CLASS__,
    required: ['id', 'name'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            example: 1
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'users.manage'
        ),
        new OA\Property(
            property: 'guard_name',
            type: 'string',
            example: 'api'
        ),
    ]
)]
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'guard_name' => $this->resource->guard_name,
        ];
    }
}
