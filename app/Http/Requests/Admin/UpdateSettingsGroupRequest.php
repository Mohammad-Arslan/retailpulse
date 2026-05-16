<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\Settings\SettingGroupRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSettingsGroupRequest extends FormRequest
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
        $group = (string) $this->route('group');
        $fields = SettingGroupRegistry::fields($group);
        $rules = [
            'values' => ['required', 'array'],
        ];

        foreach ($fields as $key => $field) {
            $fieldRules = $field['rules'] ?? ['nullable'];
            $rules["values.{$key}"] = $fieldRules;
        }

        if (isset($fields['disk'])) {
            $rules['values.disk'][] = Rule::in(['local', 's3', 'minio', 'sftp']);
        }

        return $rules;
    }
}
