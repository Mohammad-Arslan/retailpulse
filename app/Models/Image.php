<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'imageable_type',
    'imageable_id',
    'disk',
    'path',
    'thumbnail_path',
    'original_filename',
    'mime_type',
    'size',
    'width',
    'height',
    'sort_order',
    'is_primary',
    'alt',
])]
class Image extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): ?string
    {
        return $this->diskUrl($this->path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->diskUrl($this->thumbnail_path ?? $this->path);
    }

    private function diskUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk($this->disk);

        if (! $disk instanceof FilesystemAdapter) {
            return null;
        }

        $url = $disk->url($path);

        // Prefer root-relative URLs for local public media so thumbnails load on any host.
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parts = parse_url($url);
            $relative = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

            return $relative !== '' ? $relative : $url;
        }

        return $url;
    }
}
