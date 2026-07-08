<?php

declare(strict_types=1);

namespace App\Http\Requests\HelpSupport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AskGuideQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1200'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
        ];
    }
}

