<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Network\Http\Resources\NetworkConfigResource;
use OpenApi\Attributes as OA;

class NetworkConfigController extends Controller
{
    #[OA\Get(
        path: '/api/v1/network/config',
        summary: 'Show network configuration',
        operationId: 'network.config',
        tags: ['Network'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Network configuration',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: NetworkConfigResource::class),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(): NetworkConfigResource
    {
        return NetworkConfigResource::make(null);
    }
}
