<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Image;
use App\Repositories\Contracts\ImageRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image as ImageFacade;
use Intervention\Image\Interfaces\ImageInterface;

final class ImageService
{
    public function __construct(
        private readonly ImageRepositoryInterface $images,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, Image>
     */
    public function attachMany(Model $model, array $files, ?string $alt = null): Collection
    {
        $stored = collect();
        $existingCount = $this->images->countForModel($model);
        $maxImages = (int) config('media.max_images_per_model', 10);
        $remaining = max(0, $maxImages - $existingCount);

        foreach (array_slice($files, 0, $remaining) as $index => $file) {
            $stored->push($this->storeUpload(
                $model,
                $file,
                $existingCount + $index,
                $existingCount === 0 && $index === 0,
                $alt,
            ));
        }

        return $stored;
    }

    public function attach(Model $model, UploadedFile $file, ?string $alt = null): Image
    {
        $existingCount = $this->images->countForModel($model);
        $maxImages = (int) config('media.max_images_per_model', 10);

        if ($existingCount >= $maxImages) {
            abort(422, __('Maximum Number Of Images Reached.'));
        }

        return $this->storeUpload(
            $model,
            $file,
            $existingCount,
            $existingCount === 0,
            $alt,
        );
    }

    public function replaceByAlt(Model $model, UploadedFile $file, string $alt): Image
    {
        $existing = Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->where('alt', $alt)
            ->get();

        foreach ($existing as $image) {
            $this->delete($image);
        }

        return $this->attach($model, $file, $alt);
    }

    /**
     * @param  list<int>  $ids
     */
    public function removeMany(Model $model, array $ids): void
    {
        $images = Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->whereIn('id', $ids)
            ->get();

        foreach ($images as $image) {
            $this->delete($image);
        }

        $this->ensurePrimary($model);
    }

    public function purgeFor(Model $model): void
    {
        $images = Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->get();

        foreach ($images as $image) {
            $this->deleteFiles($image);
        }

        Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey())
            ->delete();
    }

    public function delete(Image $image): void
    {
        $this->deleteFiles($image);
        $this->images->delete($image);
    }

    private function storeUpload(
        Model $model,
        UploadedFile $file,
        int $sortOrder,
        bool $isPrimary,
        ?string $alt = null,
    ): Image {
        $disk = (string) config('media.disk', 'public');
        $directory = $this->directoryFor($model);
        $basename = Str::ulid()->toString();

        $mainImage = ImageFacade::decodePath($file->getRealPath());
        $mainImage->scaleDown(
            (int) config('media.max_width', 1200),
            (int) config('media.max_height', 1200),
        );

        $extension = $this->resolveExtension($file, $mainImage);
        $mainPath = "{$directory}/{$basename}.{$extension}";
        $thumbPath = "{$directory}/{$basename}_thumb.{$extension}";

        $encodedMain = $this->encode($mainImage, $extension);
        Storage::disk($disk)->put($mainPath, $encodedMain);

        $thumbnail = ImageFacade::decodePath($file->getRealPath());
        $thumbnail->cover(
            (int) config('media.thumbnail_width', 300),
            (int) config('media.thumbnail_height', 300),
        );
        Storage::disk($disk)->put($thumbPath, $this->encode($thumbnail, $extension));

        if ($isPrimary) {
            $this->images->clearPrimaryForModel($model);
        }

        return $this->images->create([
            'imageable_type' => $model->getMorphClass(),
            'imageable_id' => $model->getKey(),
            'disk' => $disk,
            'path' => $mainPath,
            'thumbnail_path' => $thumbPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'image/jpeg',
            'size' => strlen($encodedMain),
            'width' => $mainImage->width(),
            'height' => $mainImage->height(),
            'sort_order' => $sortOrder,
            'is_primary' => $isPrimary,
            'alt' => $alt,
        ]);
    }

    private function directoryFor(Model $model): string
    {
        $folder = Str::plural(Str::kebab(class_basename($model)));

        return "media/{$folder}/{$model->getKey()}";
    }

    private function resolveExtension(UploadedFile $file, ImageInterface $image): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return $image->origin()->mediaType() === 'image/png' ? 'png' : 'jpg';
    }

    private function encode(ImageInterface $image, string $extension): string
    {
        $quality = (int) config('media.quality', 85);

        $encoded = match ($extension) {
            'png' => $image->encodeUsingFileExtension('png'),
            'webp' => $image->encodeUsingFileExtension('webp', $quality),
            default => $image->encodeUsingFileExtension('jpg', $quality),
        };

        return $encoded->toString();
    }

    private function deleteFiles(Image $image): void
    {
        $disk = Storage::disk($image->disk);

        if ($image->path !== '') {
            $disk->delete($image->path);
        }

        if ($image->thumbnail_path !== null && $image->thumbnail_path !== '') {
            $disk->delete($image->thumbnail_path);
        }
    }

    private function ensurePrimary(Model $model): void
    {
        $query = Image::query()
            ->where('imageable_type', $model->getMorphClass())
            ->where('imageable_id', $model->getKey());

        if ($query->clone()->where('is_primary', true)->exists()) {
            return;
        }

        $first = $query->orderBy('sort_order')->first();

        if ($first !== null) {
            $this->images->setPrimary($first);
        }
    }
}
