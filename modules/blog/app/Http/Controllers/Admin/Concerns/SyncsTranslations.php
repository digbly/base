<?php

namespace Modules\Blog\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SyncsTranslations
{
    /**
     * Upsert the submitted translations by locale and drop the locales that are
     * no longer present in the payload.
     *
     * @param  array<int, array<string, mixed>>  $translations
     */
    protected function syncTranslations(Model $model, array $translations): void
    {
        $locales = [];

        foreach ($translations as $translation) {
            $locales[] = $translation['locale'];

            $model->translations()->updateOrCreate(
                ['locale' => $translation['locale']],
                $translation
            );
        }

        $model->translations()->whereNotIn('locale', $locales)->delete();
    }
}
