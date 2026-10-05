<?php

namespace Modules\Blog\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    required: ['content'],
    properties: [
        new OA\Property(property: 'parent_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'john@example.com'),
        new OA\Property(property: 'content', type: 'string', example: 'Great article!'),
    ]
)]
class StoreCommentRequest extends FormRequest
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
            'parent_id' => ['nullable', 'uuid', Rule::exists('comments', 'id')],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
        ];
    }
}
