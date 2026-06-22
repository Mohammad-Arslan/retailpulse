<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.grn_item_id' => ['required', 'integer'],
            'lines.*.product_variant_id' => ['required', 'integer'],
            'lines.*.qty_returned' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.line_total' => ['required', 'numeric'],
        ];
    }
}
