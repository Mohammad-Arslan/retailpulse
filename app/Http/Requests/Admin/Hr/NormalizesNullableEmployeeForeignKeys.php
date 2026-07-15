<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;

abstract class NormalizesNullableEmployeeForeignKeys extends FormRequest
{
    /**
     * @param  list<string>  $fields
     */
    protected function normalizeNullableIntegers(array $fields): void
    {
        $merged = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            if ($value === '' || $value === null) {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
