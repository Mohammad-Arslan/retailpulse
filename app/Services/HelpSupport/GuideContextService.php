<?php

declare(strict_types=1);

namespace App\Services\HelpSupport;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

final class GuideContextService
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{title: string, source: string, excerpt: string}
     */
    public function get(string $guide): array
    {
        $guide = trim($guide);

        return match ($guide) {
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
            'accounting' => $this->fromAccountingGuide(),
            default => throw new \InvalidArgumentException('Unknown guide.'),
        };
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
            'excerpt' => $this->makeExcerptFromMarkdown($markdown),
        ];
    }

    /**
     * @return array{title: string, source: string, excerpt: string}
     */
    private function fromAccountingGuide(): array
    {
        $jsonPath = base_path('resources/js/data/accountingGuide.sections.json');
        $sections = json_decode($this->files->get($jsonPath), true);

        if (! is_array($sections)) {
            throw new \RuntimeException('Accounting guide sections JSON is invalid.');
        }

        $lines = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = (string) Arr::get($section, 'title', '');
            $intro = (string) Arr::get($section, 'intro', '');

            if ($title !== '') {
                $lines[] = '## '.$title;
            }

            if ($intro !== '') {
                $lines[] = $this->stripHtmlToText($intro);
            }

            $blocks = Arr::get($section, 'blocks', []);
            if (! is_array($blocks)) {
                continue;
            }

            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }

                $blockTitle = (string) Arr::get($block, 'title', '');
                if ($blockTitle !== '') {
                    $lines[] = '### '.$blockTitle;
                }

                $type = (string) Arr::get($block, 'type', '');

                if ($type === 'note') {
                    $text = (string) Arr::get($block, 'text', '');
                    if ($text !== '') {
                        $lines[] = $this->stripHtmlToText($text);
                    }
                }

                if ($type === 'steps') {
                    $items = Arr::get($block, 'items', []);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (! is_string($item) || $item === '') {
                                continue;
                            }
                            $lines[] = '- '.$this->stripHtmlToText($item);
                        }
                    }
                }

                if ($type === 'table') {
                    $rows = Arr::get($block, 'rows', []);
                    if (is_array($rows)) {
                        foreach ($rows as $row) {
                            if (! is_array($row) || $row === []) {
                                continue;
                            }
                            $cells = array_values(array_filter($row, fn ($v) => is_string($v) && $v !== ''));
                            if ($cells !== []) {
                                $lines[] = '- '.implode(' — ', array_map([$this, 'stripHtmlToText'], $cells));
                            }
                        }
                    }
                }
            }
        }

        $text = implode("\n\n", array_values(array_filter($lines, fn ($l) => trim((string) $l) !== '')));

        return [
            'title' => 'Accounting & Financial Management',
            'source' => 'resources/js/data/accountingGuide.sections.json',
            'excerpt' => $this->makeExcerptFromMarkdown($text),
        ];
    }

    private function makeExcerptFromMarkdown(string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $markdown = preg_replace('/^```[\\s\\S]*?^```/m', '', $markdown) ?? $markdown;
        $markdown = preg_replace('/^\\|.*\\|\\s*\\n^\\|[-:|\\s]+\\|\\s*\\n(?:^\\|.*\\|\\s*\\n)*/m', '', $markdown) ?? $markdown;
        $markdown = trim($markdown);

        // Keep context token-safe for prompts.
        $maxChars = 18000;
        if (mb_strlen($markdown) > $maxChars) {
            $markdown = mb_substr($markdown, 0, $maxChars - 200).'...';
        }

        return $markdown;
    }

    private function stripHtmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html));
        $text = preg_replace('/\\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function relativeToBase(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}

