<?php

namespace Modules\Network\Http\Requests\Admin;

use Modules\Network\Enums\WebsiteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * Validates the "create my own website" flow used by the website picker. The
 * owner is always the authenticated user, so `user_id` is not accepted here.
 */
#[OA\Schema(
    schema: __CLASS__,
    required: ['title', 'subdomain', 'status'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 120, example: 'My Website'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'subdomain', type: 'string', maxLength: 32, example: 'my-site'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'suspended']),
    ]
)]
class StoreOwnWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'subdomain' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:websites,subdomain'],
            'status' => ['required', Rule::enum(WebsiteStatus::class)],
        ];
    }
}
