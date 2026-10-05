<?php

namespace App\Http\Controllers;

use App\Contracts\Setting as SettingContract;
use App\Http\Resources\SettingResource;
use OpenApi\Attributes as OA;

class SettingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/settings',
        summary: 'List public settings',
        operationId: 'settings.index',
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings exposed to the public API for the current website and locale',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: SettingResource::class),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(SettingContract $settings): SettingResource
    {
        $definitions = $settings->settings()
            ->filter(fn (array $definition) => $definition['show_api'] ?? true);

        $values = $definitions->map(
            fn (array $definition, string $key) => match ($definition['type'] ?? 'string') {
                'boolean' => $settings->boolean($key),
                'integer' => $settings->integer($key),
                'float' => $settings->float($key),
                default => $settings->get($key),
            }
        );

        return SettingResource::make($values->all());
    }
}
