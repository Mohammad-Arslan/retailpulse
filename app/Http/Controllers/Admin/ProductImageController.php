<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncProductImagesRequest;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;

final class ProductImageController extends Controller
{
    public function __construct(
        private readonly ImageService $images,
    ) {}

    public function sync(SyncProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $removeIds = array_map(
            static fn (mixed $id): int => (int) $id,
            $request->validated('remove_image_ids', []),
        );

        if ($removeIds !== []) {
            $this->images->removeMany($product, $removeIds);
        }

        $files = $request->file('images');

        if (is_array($files) && $files !== []) {
            $this->images->attachMany(
                $product,
                array_values(array_filter(
                    $files,
                    static fn (mixed $file): bool => $file !== null,
                )),
            );
        }

        return response()->json([
            'message' => __('Product images updated.'),
        ]);
    }
}
