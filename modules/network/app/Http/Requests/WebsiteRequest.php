<?php

namespace Modules\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Database;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    required: ['title', 'subdomain', 'status', 'user_id'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 120, example: 'My Website'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'subdomain', type: 'string', maxLength: 32, example: 'my-site'),
        new OA\Property(property: 'domain', type: 'string', maxLength: 64, nullable: true, example: 'my-site.com'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended']),
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'language', type: 'string', maxLength: 10, nullable: true, example: 'en'),
        new OA\Property(property: 'theme', type: 'string', maxLength: 10, nullable: true),
        new OA\Property(property: 'database', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'setup', type: 'boolean'),
        new OA\Property(property: 'is_demo', type: 'boolean'),
    ]
)]
class WebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $website = $this->route('website');
        $ignoreId = $website instanceof Website ? $website->getKey() : $website;

        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'subdomain' => [
                'required',
                'string',
                'max:32',
                'alpha_dash',
                Rule::unique('websites', 'subdomain')->ignore($ignoreId),
            ],
            'domain' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('websites', 'domain')->ignore($ignoreId),
            ],
            'status' => ['required', Rule::enum(WebsiteStatus::class)],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'theme' => ['nullable', 'string', 'max:10'],
            'database' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists((new Database)->getTable(), 'name'),
            ],
            'setup' => ['sometimes', 'boolean'],
            'is_demo' => ['sometimes', 'boolean'],
        ];
    }
}
