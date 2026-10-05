<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    properties: [
        new OA\Property(property: 'title', type: 'object', example: ['en' => 'My site', 'vi' => 'Trang của tôi']),
        new OA\Property(property: 'description', type: 'object', example: ['en' => 'A short description']),
        new OA\Property(property: 'sitename', type: 'string', nullable: true),
        new OA\Property(property: 'logo', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'favicon', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'banner', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'user_registration', type: 'boolean', nullable: true),
        new OA\Property(property: 'user_verification', type: 'boolean', nullable: true),
    ]
)]
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
