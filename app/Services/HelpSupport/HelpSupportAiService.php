<?php

declare(strict_types=1);

namespace App\Services\HelpSupport;

use App\Ai\Agents\GuideAssistantAgent;
use Illuminate\Support\Arr;
use Laravel\Ai\Responses\StreamableAgentResponse;

final class HelpSupportAiService
{
    public function __construct(
        private readonly GuideContextService $guideContext,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>|null  $history
     */
    public function streamGuideAnswer(string $guide, string $message, ?array $history = null): StreamableAgentResponse
    {
        $ctx = $this->guideContext->get($guide);

        $prompt = $this->buildPrompt(
            question: $message,
            history: $history ?? [],
        );

        return (new GuideAssistantAgent(
            guideTitle: $ctx['title'],
            guideSource: $ctx['source'],
            guideExcerpt: $ctx['excerpt'],
        ))->stream($prompt);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function buildPrompt(string $question, array $history): string
    {
        $question = trim($question);

        $lines = [];

        $history = array_slice($history, -8);
        foreach ($history as $turn) {
            $role = (string) Arr::get($turn, 'role', '');
            $content = trim((string) Arr::get($turn, 'content', ''));
            if ($content === '' || ($role !== 'user' && $role !== 'assistant')) {
                continue;
            }
            $lines[] = strtoupper($role).': '.$content;
        }

        $lines[] = 'USER: '.$question;

        return implode("\n", $lines);
    }
}

