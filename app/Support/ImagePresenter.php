<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Collection;

final class ImagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forImage(Image $image): array
    {
        return [
            'id' => $image->id,
            'url' => $image->url(),
            'thumbnail_url' => $image->thumbnailUrl(),
            'original_filename' => $image->original_filename,
            'alt' => $image->alt,
            'is_primary' => $image->is_primary,
            'sort_order' => $image->sort_order,
            'width' => $image->width,
            'height' => $image->height,
        ];
    }

    /**
     * @param  Collection<int, Image>|iterable<Image>  $images
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $images): array
    {
        $items = [];

        foreach ($images as $image) {
            $items[] = self::forImage($image);
        }

        return $items;
    }
}
