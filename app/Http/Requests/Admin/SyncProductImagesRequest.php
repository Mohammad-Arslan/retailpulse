<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Image;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.config('media.max_upload_kb', 5120)],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', Rule::exists('images', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Product|null $product */
            $product = $this->route('product');

            if ($product === null) {
                return;
            }

            $removeIds = $this->input('remove_image_ids', []);

            if (is_array($removeIds) && $removeIds !== []) {
                $validCount = Image::query()
                    ->where('imageable_type', $product->getMorphClass())
                    ->where('imageable_id', $product->id)
                    ->whereIn('id', $removeIds)
                    ->count();

                if ($validCount !== count($removeIds)) {
                    $validator->errors()->add(
                        'remove_image_ids',
                        __('One or more selected images are invalid.'),
                    );
                }
            }

            $remaining = $product->images()->count()
                - (is_array($removeIds) ? count($removeIds) : 0)
                + count($this->file('images') ?? []);

            if ($remaining > (int) config('media.max_images_per_model', 10)) {
                $validator->errors()->add(
                    'images',
                    __('You can upload at most :count images per product.', [
                        'count' => config('media.max_images_per_model', 10),
                    ]),
                );
            }
        });
    }
}
