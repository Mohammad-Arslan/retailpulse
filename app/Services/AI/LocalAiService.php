<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Ai\Agents\LocalDevAssistant;
use App\Contracts\AI\LocalAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class LocalAiService implements LocalAiClient
{
    public function ask(string $prompt): string
    {
        return $this->prompt($this->sanitizeUserText($prompt));
    }

    public function summarize(string $text): string
    {
        $safe = $this->sanitizeUserText($text);

        return $this->prompt(
            "Summarize the following text for a Laravel/PHP developer. Keep it short and structured with bullets where useful.\n\n".$safe
        );
    }

    private function prompt(string $prompt): string
    {
        $provider = (string) config('ai.default', 'ollama');
        $model = $this->resolveModel($provider);
        $baseUrl = (string) data_get(config('ai.providers.'.$provider), 'url', '');

        try {
            $response = (new LocalDevAssistant)->prompt(
                $prompt,
                provider: $provider,
                model: $model,
                timeout: 120,
            );

            $answer = trim((string) $response);

            if ($answer === '') {
                throw new RuntimeException('AI provider returned an empty response.');
            }

            return $answer;
        } catch (ConnectionException $e) {
            Log::warning('Local AI connection failed.', [
                'provider' => $provider,
                'model' => $model,
                'url' => $baseUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Could not connect to the local AI provider. Ensure Ollama is running at '.$baseUrl.'.',
                previous: $e,
            );
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $body = (string) ($e->response?->body() ?? '');
            $lower = strtolower($body);

            Log::warning('Local AI request failed.', [
                'provider' => $provider,
                'model' => $model,
                'status' => $status,
                'body' => mb_substr($body, 0, 500),
            ]);

            if ($status === 404 || str_contains($lower, 'not found') || str_contains($lower, 'model')) {
                throw new RuntimeException(
                    "AI model [{$model}] was not found. Run: ollama pull {$model}",
                    previous: $e,
                );
            }

            throw new RuntimeException(
                'Local AI request failed'.($status ? " (HTTP {$status})" : '').'.',
                previous: $e,
            );
        } catch (Throwable $e) {
            Log::error('Local AI prompt failed.', [
                'provider' => $provider,
                'model' => $model,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Local AI request failed: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function resolveModel(string $provider): string
    {
        $configured = data_get(config('ai.providers.'.$provider), 'models.text.default');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return (string) env('OLLAMA_MODEL', 'qwen2.5-coder:7b');
    }

    /**
     * Redact likely secrets from free-form prompts before they leave the machine.
     */
    private function sanitizeUserText(string $text): string
    {
        $text = trim($text);

        $patterns = [
            '/(?i)(api[_-]?key|secret|password|token|authorization)\s*[:=]\s*\S+/',
            '/(?i)(DB_PASSWORD|DB_USERNAME|APP_KEY|DEEPSEEK_API_KEY|OPENAI_API_KEY|AWS_SECRET_ACCESS_KEY)\s*=\s*\S+/',
            '/sk-[a-zA-Z0-9]{20,}/',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[REDACTED]', $text) ?? $text;
        }

        return $text;
    }
}
