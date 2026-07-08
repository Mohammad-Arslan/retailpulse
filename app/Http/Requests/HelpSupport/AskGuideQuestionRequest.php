<?php

declare(strict_types=1);

namespace App\Http\Requests\HelpSupport;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            // Assistant replies can exceed the user message cap (agent MaxTokens is 1400+).
            'history.*.content' => ['required_with:history', 'string', 'max:16000'],
        ];
    }

    /**
     * Always return JSON for this fetch/SSE endpoint — Accept prefers text/event-stream,
     * so Laravel would otherwise 302-redirect on validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first() ?: 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Unauthenticated.',
        ], 401));
    }
}

