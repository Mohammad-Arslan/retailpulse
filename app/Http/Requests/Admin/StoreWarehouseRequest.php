<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\BranchContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('warehouses.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();

                    if ($user === null) {
                        return;
                    }

                    $accessibleIds = app(BranchContextService::class)->accessibleBranchIds($user);

                    if ($accessibleIds !== null && ! in_array((int) $value, $accessibleIds, true)) {
                        $fail(__('You do not have access to this branch.'));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }
}
