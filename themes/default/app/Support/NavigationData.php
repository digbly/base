<?php

namespace Themes\Default\Support;

use Illuminate\Support\Collection;
use Modules\Blog\Models\Category;

class NavigationData
{
    /**
     * @return Collection<int, Category>
     */
    public function categories(int $limit = 6): Collection
    {
        return Category::query()
            ->with('translations')
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
