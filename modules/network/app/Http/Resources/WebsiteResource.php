<?php

namespace Modules\Network\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

/**
 * @property-read Website $resource
 */
#[OA\Schema(
    schema: __CLASS__,
    required: ['id', 'title', 'subdomain', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'My Website'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'subdomain', type: 'string', example: 'my-site'),
        new OA\Property(property: 'domain', type: 'string', nullable: true, example: 'my-site.com'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended']),
        new OA\Property(property: 'status_label', type: 'string', example: 'Active'),
        new OA\Property(property: 'setup', type: 'boolean'),
        new OA\Property(property: 'is_demo', type: 'boolean'),
        new OA\Property(property: 'language', type: 'string', nullable: true, example: 'en'),
        new OA\Property(property: 'theme', type: 'string', nullable: true),
        new OA\Property(property: 'database', type: 'string', nullable: true),
        new OA\Property(property: 'url', type: 'string', nullable: true),
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'owner', type: UserResource::class, nullable: true),
        new OA\Property(property: 'users_count', type: 'integer', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'subdomain' => $this->subdomain,
            'domain' => $this->domain,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'setup' => $this->setup,
            'is_demo' => $this->is_demo,
            'language' => $this->language,
            'theme' => $this->theme,
            'database' => $this->database,
            'url' => $this->url,
            'user_id' => $this->user_id,
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
