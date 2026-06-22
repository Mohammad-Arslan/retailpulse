<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\LocaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SwitchLocaleRequest extends FormRequest
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
            'locale' => [
                'required',
                'string',
                'max:12',
                Rule::in(app(LocaleService::class)->enabledLocaleCodes()),
            ],
        ];
    }
}
