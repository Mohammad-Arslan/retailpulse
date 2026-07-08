<?php

declare(strict_types=1);

namespace Tests\Feature\Dev;

use App\Contracts\AI\LocalAiClient;
use Mockery;
use Tests\TestCase;

final class LocalAiAskTest extends TestCase
{
    public function test_ask_requires_a_prompt(): void
    {
        $this->app['env'] = 'local';

        $response = $this->postJson('/api/dev/ai/ask', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_ask_rejects_prompts_that_are_too_long(): void
    {
        $this->app['env'] = 'local';

        $response = $this->postJson('/api/dev/ai/ask', [
            'prompt' => str_repeat('a', 4001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_ask_returns_success_json_when_service_answers(): void
    {
        $this->app['env'] = 'local';

        $mock = Mockery::mock(LocalAiClient::class);
        $mock->shouldReceive('ask')
            ->once()
            ->with('Explain Laravel service container in simple words')
            ->andReturn('The container resolves and injects your class dependencies.');

        $this->app->instance(LocalAiClient::class, $mock);

        $response = $this->postJson('/api/dev/ai/ask', [
            'prompt' => 'Explain Laravel service container in simple words',
        ]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'answer' => 'The container resolves and injects your class dependencies.',
            ]);
    }

    public function test_ask_is_not_available_outside_local(): void
    {
        $this->app['env'] = 'testing';

        $response = $this->postJson('/api/dev/ai/ask', [
            'prompt' => 'Hello',
        ]);

        $response->assertNotFound();
    }
}
