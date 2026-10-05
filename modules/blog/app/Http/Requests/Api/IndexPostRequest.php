<?php

namespace Modules\Blog\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    properties: [
        new OA\Property(property: 'search', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'news'),
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 50, nullable: true),
        new OA\Property(property: 'page', type: 'integer', minimum: 1, nullable: true),
    ]
)]
class IndexPostRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:190'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
