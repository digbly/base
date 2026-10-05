<?php

namespace Modules\Blog\Http\Requests\Admin\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait ValidatesTranslations
{
    /**
     * Skip the website constraint when no website is resolved (console/tests),
     * otherwise scope uniqueness and existence checks to the current website.
     *
     * @return array<int, mixed>
     */
    protected function existsInWebsite(string $table): array
    {
        $rule = Rule::exists($table, 'id');

        if (($websiteId = website_id()) !== null) {
            $rule->where('website_id', $websiteId);
        }

        return [$rule];
    }

    /**
     * Reject duplicate translation slugs inside the same website, ignoring the
     * translations of the record being updated.
     *
     * @param  class-string<Model>  $translationModel
     */
    protected function uniqueSlugRule(
        string $translationModel,
        string $foreignKey,
        ?string $ignoreId = null
    ): Closure {
        return function (string $attribute, mixed $value, Closure $fail) use (
            $translationModel,
            $foreignKey,
            $ignoreId
        ): void {
            $query = $translationModel::query()->where('slug', $value);

            if (($websiteId = website_id()) !== null) {
                $query->where('website_id', $websiteId);
            }

            if ($ignoreId !== null) {
                $query->where($foreignKey, '!=', $ignoreId);
            }

            if ($query->exists()) {
                $fail('The slug has already been taken.');
            }
        };
    }
}
