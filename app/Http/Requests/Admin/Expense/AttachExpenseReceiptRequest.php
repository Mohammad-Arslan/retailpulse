<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;

final class AttachExpenseReceiptRequest extends FormRequest
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
            'receipt' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }
}
