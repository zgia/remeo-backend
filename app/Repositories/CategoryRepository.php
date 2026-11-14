<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;

class CategoryRepository
{
    /** @return array<int,array<string,mixed>> */
    public function ordered(): array
    {
        return Category::query()
            ->orderBy('parentid')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->map(fn (Category $record): array => $record->getAttributes())
            ->all();
    }
}
