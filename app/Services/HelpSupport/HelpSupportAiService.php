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
        $ctx = $this->guideContext->get($guide, $message);

        $prompt = $this->buildPrompt(
            question: $message,
            history: $history ?? [],
            guideTitle: $ctx['title'],
        );

        $provider = (string) config('ai.default', 'ollama');
        $model = $this->resolveModel($provider);

        return (new GuideAssistantAgent(
            guideTitle: $ctx['title'],
            guideSource: $ctx['source'],
            guideExcerpt: $ctx['excerpt'],
        ))->stream(
            $prompt,
            provider: $provider,
            model: $model,
            timeout: 120,
        );
    }

    private function resolveModel(string $provider): string
    {
        $configured = data_get(config('ai.providers.'.$provider), 'models.text.default');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        // Sensible defaults if a provider has no models.text.default configured.
        return match ($provider) {
            'deepseek' => 'deepseek-chat',
            'openai' => 'gpt-4o-mini',
            default => (string) env('OLLAMA_MODEL', 'qwen2.5-coder:7b'),
        };
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function buildPrompt(string $question, array $history, string $guideTitle): string
    {
        $question = trim($question);

        $lines = [
            'Use ONLY the GUIDE CONTEXT in your system instructions for the RetailPulse guide "'.$guideTitle.'".',
            'If the answer is not in that guide context, say so. Do not invent RetailPulse features.',
            '',
        ];

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
        $lines[] = 'ASSISTANT:';

        return implode("\n", $lines);
    }
}
