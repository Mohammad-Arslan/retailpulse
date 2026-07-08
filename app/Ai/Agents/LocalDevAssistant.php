<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[MaxTokens(1200)]
#[Temperature(0.2)]
#[Timeout(120)]
final class LocalDevAssistant implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a helpful Laravel and PHP development assistant for local RetailPulse development.
Answer clearly and concisely. Prefer practical explanations, short examples, and correct Laravel conventions.
Do not invent APIs that do not exist. If something is unclear, say so briefly.
PROMPT;
    }
}
