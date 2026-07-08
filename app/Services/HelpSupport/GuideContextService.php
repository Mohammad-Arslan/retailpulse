<?php

declare(strict_types=1);

namespace App\Services\HelpSupport;

use App\Exceptions\HelpSupport\UnknownGuideException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class GuideContextService
{
    private const MAX_EXCERPT_CHARS = 32000;

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{title: string, source: string, excerpt: string}
     */
    public function get(string $guide, ?string $question = null): array
    {
        $guide = trim($guide);

        $ctx = match ($guide) {
            'put-product-in-stock' => $this->fromMarkdownDoc(
                base_path('docs/user-manual-put-product-in-stock.md'),
                title: 'Put a Product in Stock (Any Branch)',
            ),
            'inventory-catalogue' => $this->fromMarkdownDoc(
                base_path('docs/user-manual-inventory-and-catalogue.md'),
                title: 'Catalogue & Inventory Operations',
            ),
            'customers-loyalty' => $this->fromMarkdownDoc(
                base_path('docs/user-manual-customers-and-loyalty.md'),
                title: 'Customers & Loyalty',
            ),
            'accounting' => $this->fromMarkdownDoc(
                base_path('docs/user-manual-accounting-and-finance.md'),
                title: 'Accounting & Financial Management',
            ),
            default => throw new UnknownGuideException,
        };

        $ctx['excerpt'] = $this->prioritizeExcerpt($ctx['excerpt'], $question);

        return $ctx;
    }

    /**
     * @return array{title: string, source: string, excerpt: string}
     */
    private function fromMarkdownDoc(string $path, string $title): array
    {
        $markdown = $this->files->get($path);

        return [
            'title' => $title,
            'source' => str_replace('\\', '/', $this->relativeToBase($path)),
            'excerpt' => $this->normalizeGuideText($markdown),
        ];
    }

    private function normalizeGuideText(string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        // Keep fenced examples out of the prompt (code fences are noisy for Q&A).
        $markdown = preg_replace('/^```[\s\S]*?^```/m', '', $markdown) ?? $markdown;
        $markdown = trim($markdown);

        return $markdown;
    }

    /**
     * Keep the excerpt token-safe. When a question is present, keep the most
     * relevant sections first so the model reads matching RetailPulse guide text.
     */
    private function prioritizeExcerpt(string $markdown, ?string $question): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }

        if (mb_strlen($markdown) <= self::MAX_EXCERPT_CHARS) {
            return $markdown;
        }

        $terms = $this->questionTerms($question);
        if ($terms === []) {
            return mb_substr($markdown, 0, self::MAX_EXCERPT_CHARS - 200).'...';
        }

        $chunks = preg_split('/\n(?=##\s+)/', $markdown) ?: [$markdown];
        $scored = [];

        foreach ($chunks as $index => $chunk) {
            $lower = Str::lower($chunk);
            $score = 0;
            foreach ($terms as $term) {
                $score += substr_count($lower, $term) * max(1, mb_strlen($term));
            }
            $scored[] = ['index' => $index, 'score' => $score, 'chunk' => $chunk];
        }

        usort($scored, function (array $a, array $b): int {
            return $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index'];
        });

        $selected = [];
        $used = 0;
        foreach ($scored as $item) {
            $chunk = trim((string) $item['chunk']);
            if ($chunk === '') {
                continue;
            }
            $len = mb_strlen($chunk) + 2;
            if ($used > 0 && $used + $len > self::MAX_EXCERPT_CHARS) {
                continue;
            }
            $selected[] = $chunk;
            $used += $len;
            if ($used >= self::MAX_EXCERPT_CHARS) {
                break;
            }
        }

        if ($selected === []) {
            return mb_substr($markdown, 0, self::MAX_EXCERPT_CHARS - 200).'...';
        }

        usort($selected, function (string $a, string $b) use ($chunks): int {
            return array_search($a, $chunks, true) <=> array_search($b, $chunks, true);
        });

        $excerpt = implode("\n\n", $selected);
        if (mb_strlen($excerpt) > self::MAX_EXCERPT_CHARS) {
            $excerpt = mb_substr($excerpt, 0, self::MAX_EXCERPT_CHARS - 200).'...';
        }

        return $excerpt;
    }

    /**
     * @return list<string>
     */
    private function questionTerms(?string $question): array
    {
        $question = Str::lower(trim((string) $question));
        if ($question === '') {
            return [];
        }

        $stop = [
            'a', 'an', 'the', 'and', 'or', 'to', 'of', 'in', 'on', 'for', 'with', 'from', 'how', 'what',
            'when', 'where', 'why', 'is', 'are', 'do', 'does', 'can', 'i', 'we', 'my', 'our', 'this',
            'that', 'should', 'about', 'please', 'me', 'you', 'it', 'be', 'if', 'not', 'into',
        ];

        $parts = preg_split('/[^a-z0-9]+/i', $question) ?: [];
        $terms = [];

        foreach ($parts as $part) {
            $part = Str::lower(trim($part));
            if ($part === '' || mb_strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $terms[] = $part;
        }

        return array_values(array_unique($terms));
    }

    private function relativeToBase(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
