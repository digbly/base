<?php

namespace Modules\Admin\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    required: ['code', 'name'],
    properties: [
        new OA\Property(property: 'code', type: 'string', maxLength: 10, example: 'en'),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'English'),
        new OA\Property(property: 'is_default', type: 'boolean', example: false),
    ]
)]
class LanguageRequest extends FormRequest
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
        $language = $this->route('language');
        $ignoreId = $language instanceof Language ? $language->getKey() : $language;

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::in(array_keys(config('locales'))),
                Rule::unique('languages', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
