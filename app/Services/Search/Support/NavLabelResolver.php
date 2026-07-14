<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

use Illuminate\Support\Facades\File;

/**
 * Resolves English nav labels from the frontend locale file for page search matching.
 */
final class NavLabelResolver
{
    /** @var array<string, string>|null */
    private static ?array $navLabels = null;

    public function label(string $titleKey): string
    {
        $labels = $this->all();

        return $labels[$titleKey] ?? $this->humanize($titleKey);
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        if (self::$navLabels !== null) {
            return self::$navLabels;
        }

        $path = resource_path('js/locales/en.json');
        if (! File::exists($path)) {
            return self::$navLabels = [];
        }

        $decoded = json_decode(File::get($path), true);
        self::$navLabels = is_array($decoded['nav'] ?? null) ? $decoded['nav'] : [];

        return self::$navLabels;
    }

    private function humanize(string $key): string
    {
        return ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $key) ?? $key);
    }
}
