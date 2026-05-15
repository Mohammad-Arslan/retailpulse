<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueSlug
{
    public static function forModel(Model $model, string $name, string $column = 'slug'): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (
            $model::query()
                ->where($column, $slug)
                ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
