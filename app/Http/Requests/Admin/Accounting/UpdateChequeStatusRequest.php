<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateChequeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-cheques') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,deposited,cleared,bounced,cancelled'],
        ];
    }
}
