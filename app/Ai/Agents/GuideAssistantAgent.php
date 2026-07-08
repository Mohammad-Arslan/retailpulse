<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-chat')]
#[MaxTokens(1400)]
#[Temperature(0.3)]
#[Timeout(90)]
final class GuideAssistantAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly string $guideTitle,
        private readonly string $guideSource,
        private readonly string $guideExcerpt,
    ) {}

    public function instructions(): string
    {
        return <<<PROMPT
You are RetailPulse Guide Assistant.

You answer user questions using ONLY the provided guide context below. Provide high-level, neat, clear answers with a support tone. Use markdown headings and bullets when helpful.

Rules:
- Keep answers grounded in the guide context. Do not invent screens, buttons, or features not in the context.
- If the question is not covered, say it's not in the guide and suggest the closest relevant section or keywords to search.
- Prefer a structure: Short Answer → Steps (numbered) → Tips / Common Pitfalls.
- Be concise. Avoid long essays.

Guide:
- Title: {$this->guideTitle}
- Source: {$this->guideSource}

Guide Context (excerpt):
{$this->guideExcerpt}
PROMPT;
    }
}

