<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id;

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_.-]*$/', Rule::unique('permissions', 'name')->ignore($permissionId)],
            'display_name' => ['required', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
