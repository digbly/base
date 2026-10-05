<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Contracts\Setting as SettingContract;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Http\Requests\Admin\SettingRequest;
use Modules\Admin\Http\Resources\SettingResource;
use OpenApi\Attributes as OA;

class SettingController extends Controller
{
    public function __construct(
        protected SettingContract $settings
    ) {
        //
    }

    #[OA\Get(
        path: '/api/v1/admin/settings',
        summary: 'Show Settings',
        operationId: 'admin.settings.index',
        tags: ['Admin Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings of the website',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: SettingResource::class),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): SettingResource
    {
        return SettingResource::make($this->payload());
    }

    #[OA\Put(
        path: '/api/v1/admin/settings',
        summary: 'Update Settings',
        operationId: 'admin.settings.update',
        tags: ['Admin Settings'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: SettingRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: SettingResource::class),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(SettingRequest $request): SettingResource
    {
        $this->apply($request->validated());

        return SettingResource::make($this->payload());
    }

    /**
     * Persist validated setting values, handling translatable definitions.
     *
     * @param  array<string, mixed>  $data
     */
    protected function apply(array $data): void
    {
        $definitions = $this->settings->settings();

        DB::transaction(function () use ($data, $definitions): void {
            foreach ($data as $key => $value) {
                $definition = $definitions->get($key);

                if ($definition === null) {
                    continue;
                }

                if (($definition['translatable'] ?? false) && is_array($value)) {
                    foreach ($value as $locale => $localized) {
                        $this->settings->locale((string) $locale)->set($key, $localized);
                    }

                    continue;
                }

                $this->settings->set($key, $value);
            }
        });

        // Restore the request locale so the repository singleton (which is
        // stateful) is not left on the last edited translation.
        $this->settings->locale(app()->getLocale());
    }

    /**
     * Build the settings payload, resolving typed and localized values from
     * the registered definitions.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $definitions = $this->settings->settings();
        $translations = $this->settings->localized();
        $payload = [];

        foreach ($definitions as $key => $definition) {
            if ($definition['translatable'] ?? false) {
                /** @var Collection $values */
                $values = $translations->get($key, new Collection);

                $payload[$key] = $values->all();

                continue;
            }

            $payload[$key] = match ($definition['type'] ?? 'string') {
                'boolean' => $this->settings->boolean($key),
                'integer' => $this->settings->integer($key),
                'float' => $this->settings->float($key),
                default => $this->settings->get($key),
            };
        }

        return $payload;
    }
}
