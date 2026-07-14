<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetDisposalRequest extends FormRequest
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
            'disposal_date' => ['required', 'date'],
            'proceeds_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
