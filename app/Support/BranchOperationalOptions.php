<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SystemSetting;

final class BranchOperationalOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function currencyOptions(): array
    {
        return self::mapOptions(config('branches.currencies', []));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function timezoneOptions(): array
    {
        return self::mapOptions(config('branches.timezones', []));
    }

    public static function defaultCurrency(): string
    {
        $default = (string) SystemSetting::get(
            'general',
            'default_currency',
            array_key_first(config('branches.currencies', [])) ?: 'USD',
        );

        return self::normalizeCurrency($default);
    }

    public static function defaultTimezone(): string
    {
        $default = (string) SystemSetting::get(
            'general',
            'default_timezone',
            array_key_first(config('branches.timezones', [])) ?: 'UTC',
        );

        return self::normalizeTimezone($default);
    }

    /**
     * @return array{
     *     currencies: list<array{value: string, label: string}>,
     *     timezones: list<array{value: string, label: string}>,
     *     defaults: array{currency: string, timezone: string}
     * }
     */
    public static function formPayload(): array
    {
        return [
            'currencies' => self::currencyOptions(),
            'timezones' => self::timezoneOptions(),
            'defaults' => [
                'currency' => self::defaultCurrency(),
                'timezone' => self::defaultTimezone(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedCurrencyCodes(): array
    {
        return array_keys(config('branches.currencies', []));
    }

    /**
     * @return list<string>
     */
    public static function allowedTimezoneIdentifiers(): array
    {
        return array_keys(config('branches.timezones', []));
    }

    public static function normalizeCurrency(string $currency): string
    {
        $code = strtoupper(trim($currency));
        $allowed = self::allowedCurrencyCodes();

        if ($allowed === [] || in_array($code, $allowed, true)) {
            return $code;
        }

        $fallback = (string) SystemSetting::get('general', 'default_currency', $allowed[0] ?? 'USD');

        return in_array($fallback, $allowed, true) ? strtoupper($fallback) : ($allowed[0] ?? 'USD');
    }

    public static function normalizeTimezone(string $timezone): string
    {
        $identifier = trim($timezone);
        $allowed = self::allowedTimezoneIdentifiers();

        if ($allowed !== [] && ! in_array($identifier, $allowed, true)) {
            $fallback = (string) SystemSetting::get('general', 'default_timezone', $allowed[0] ?? 'UTC');

            return in_array($fallback, $allowed, true) ? $fallback : ($allowed[0] ?? 'UTC');
        }

        return $identifier;
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function mapOptions(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
