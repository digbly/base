<?php

namespace Modules\Admin\Http\Requests\Admin;

use App\Contracts\Setting as SettingContract;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    properties: [
        new OA\Property(property: 'title', type: 'object', example: ['en' => 'My site', 'vi' => 'Trang của tôi']),
        new OA\Property(property: 'description', type: 'object', example: ['en' => 'A short description']),
        new OA\Property(property: 'sitename', type: 'string', nullable: true, maxLength: 120),
        new OA\Property(property: 'logo', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'favicon', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'banner', type: 'string', nullable: true, format: 'uuid'),
        new OA\Property(property: 'user_registration', type: 'boolean'),
        new OA\Property(property: 'user_verification', type: 'boolean'),
    ]
)]
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Build the validation rules from the registered setting definitions.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (app(SettingContract::class)->settings() as $key => $definition) {
            $fieldRules = $definition['rules'] ?: ['nullable'];

            if ($definition['translatable'] ?? false) {
                $rules[$key] = ['sometimes', 'array'];
                $rules[$key.'.*'] = array_merge(['sometimes'], $fieldRules);

                continue;
            }

            $rules[$key] = array_merge(['sometimes'], $fieldRules);
        }

        return $rules;
    }
}
