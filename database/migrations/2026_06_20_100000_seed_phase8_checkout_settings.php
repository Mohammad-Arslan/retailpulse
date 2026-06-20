<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: mixed, 3: string}>
     */
    private const SETTINGS = [
        ['checkout', 'payment_method_cash', true, 'boolean'],
        ['checkout', 'payment_method_card', true, 'boolean'],
        ['checkout', 'payment_method_mobile_wallet', true, 'boolean'],
        ['checkout', 'payment_method_bank_transfer', true, 'boolean'],
        ['checkout', 'payment_method_credit', false, 'boolean'],
        ['checkout', 'invoice_share_email', true, 'boolean'],
        ['checkout', 'invoice_share_link', true, 'boolean'],
        ['checkout', 'invoice_share_whatsapp', false, 'boolean'],
        ['checkout', 'invoice_share_print', true, 'boolean'],
        ['checkout', 'whatsapp_api_url', '', 'string'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$group, $key, $value, $type]) {
            $exists = SystemSetting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            SystemSetting::query()->create([
                'group' => $group,
                'key' => $key,
                'value' => $this->serialize($value, $type),
                'type' => $type,
                'updated_by' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::SETTINGS as [$group, $key]) {
            SystemSetting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->delete();
        }
    }

    private function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
};
