<?php

declare(strict_types=1);

namespace App\Http\Requests\Dev;

use Illuminate\Foundation\Http\FormRequest;

final class AskLocalAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
        ];
    }
}
