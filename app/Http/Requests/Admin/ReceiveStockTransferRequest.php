<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.transfer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['sometimes', 'array'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $transfer = $this->route('stockTransfer');

            if ($transfer === null) {
                return;
            }

            foreach ((array) $this->input('lines', []) as $index => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $belongs = $transfer->items()->whereKey($itemId)->exists();

                if (! $belongs) {
                    $validator->errors()->add(
                        "lines.{$index}.item_id",
                        __('Invalid transfer line.'),
                    );
                }
            }
        });
    }
}
