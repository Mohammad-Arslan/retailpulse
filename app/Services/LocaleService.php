<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

final class LocaleService
{
    private const SESSION_KEY = 'locale';

    /**
     * @return array{code: string, label: string, native: string, rtl: bool}
     */
    public function resolve(Request $request): array
    {
        $enabled = $this->enabledLocaleCodes();
        $default = $this->defaultLocaleCode();
        $sessionLocale = $request->session()->get(self::SESSION_KEY);
        $code = is_string($sessionLocale) && in_array($sessionLocale, $enabled, true)
            ? $sessionLocale
            : $default;

        if (! in_array($code, $enabled, true)) {
            $code = $enabled[0] ?? config('locales.default', 'en');
        }

        App::setLocale($code);

        return $this->metaForCode($code);
    }

    /**
     * @return list<array{code: string, label: string, native: string, rtl: bool}>
     */
    public function switcherOptions(): array
    {
        return array_values(array_map(
            fn (string $code) => $this->metaForCode($code),
            $this->enabledLocaleCodes(),
        ));
    }

    public function switchLocale(Request $request, string $locale): void
    {
        if (! in_array($locale, $this->enabledLocaleCodes(), true)) {
            throw ValidationException::withMessages([
                'locale' => __('The selected language is not enabled.'),
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $locale);
        App::setLocale($locale);
    }

    /**
     * @return list<string>
     */
    public function enabledLocaleCodes(): array
    {
        $configured = SystemSetting::get('general', 'enabled_locales', [config('locales.default', 'en')]);

        if (! is_array($configured) || $configured === []) {
            $configured = [config('locales.default', 'en')];
        }

        $available = array_keys(config('locales.available', []));
        $enabled = array_values(array_intersect($configured, $available));

        if ($enabled === []) {
            return [config('locales.default', 'en')];
        }

        return $enabled;
    }

    public function defaultLocaleCode(): string
    {
        $default = (string) SystemSetting::get('general', 'default_locale', config('locales.default', 'en'));
        $enabled = $this->enabledLocaleCodes();

        if (in_array($default, $enabled, true)) {
            return $default;
        }

        return $enabled[0];
    }

    /**
     * @return array{code: string, label: string, native: string, rtl: bool}
     */
    private function metaForCode(string $code): array
    {
        $meta = config("locales.available.{$code}", []);

        return [
            'code' => $code,
            'label' => (string) ($meta['label'] ?? strtoupper($code)),
            'native' => (string) ($meta['native'] ?? $meta['label'] ?? strtoupper($code)),
            'rtl' => (bool) ($meta['rtl'] ?? false),
        ];
    }
}
