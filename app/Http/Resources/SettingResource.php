<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    properties: [
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'sitename', type: 'string', nullable: true),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
        new OA\Property(property: 'favicon', type: 'string', nullable: true),
        new OA\Property(property: 'banner', type: 'string', nullable: true),
        new OA\Property(property: 'user_registration', type: 'boolean'),
        new OA\Property(property: 'user_verification', type: 'boolean'),
    ]
)]
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
