<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Image;
use App\Repositories\Contracts\ImageRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

final class ImageRepository implements ImageRepositoryInterface
{
    public function create(array $attributes): Image
    {
        return Image::query()->create($attributes);
    }

    public function delete(Image $image): void
    {
        Image::destroy($image->getKey());
    }

    public function deleteByIdsForModel(Model $model, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function countForModel(Model $model): int
    {
        return Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->count();
    }

    public function clearPrimaryForModel(Model $model): void
    {
        Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->update(['is_primary' => false]);
    }

    public function setPrimary(Image $image): void
    {
        Image::query()
            ->where('imageable_type', $image->imageable_type)
            ->where('imageable_id', $image->imageable_id)
            ->update(['is_primary' => false]);

        $image->update(['is_primary' => true]);
    }
}
