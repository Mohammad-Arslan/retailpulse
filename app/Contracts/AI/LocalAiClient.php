<?php

declare(strict_types=1);

namespace App\Contracts\AI;

interface LocalAiClient
{
    public function ask(string $prompt): string;

    public function summarize(string $text): string;
}
