<?php

namespace Modules\Network\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Presents network-level configuration. Unlike a typical resource it does not
 * wrap a model, so it is instantiated without one.
 */
#[OA\Schema(
    schema: __CLASS__,
    properties: [
        new OA\Property(property: 'domain', type: 'string', nullable: true, example: 'example.com'),
        new OA\Property(property: 'subsite_domain', type: 'string', nullable: true, example: 'example.com'),
    ]
)]
class NetworkConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'domain' => config('network.domain'),
            'subsite_domain' => config('network.subsite_domain'),
        ];
    }
}
