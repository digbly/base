<?php

namespace Modules\Admin\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: __CLASS__,
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'editor'),
        new OA\Property(
            property: 'permissions',
            description: 'Permission names to assign to the role.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['users.manage', 'roles.manage']
        ),
    ]
)]
class StoreRoleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query
                        ->where('guard_name', config('auth.defaults.guard'))
                        ->where('website_id', website_id())),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')
                    ->where(fn ($query) => $query
                        ->where('guard_name', config('auth.defaults.guard'))
                        ->where(fn ($query) => $query
                            ->where('website_id', website_id())
                            ->orWhereNull('website_id'))),
            ],
        ];
    }
}
