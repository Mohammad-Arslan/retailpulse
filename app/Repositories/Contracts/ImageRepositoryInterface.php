<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;

interface ImageRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Image;

    public function delete(Image $image): void;

    /**
     * @param  list<int>  $ids
     */
    public function deleteByIdsForModel(Model $model, array $ids): int;

    public function countForModel(Model $model): int;

    public function clearPrimaryForModel(Model $model): void;

    public function setPrimary(Image $image): void;
}
