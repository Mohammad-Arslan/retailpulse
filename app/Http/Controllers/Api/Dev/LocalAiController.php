<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dev;

use App\Contracts\AI\LocalAiClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dev\AskLocalAiRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class LocalAiController extends Controller
{
    public function ask(AskLocalAiRequest $request, LocalAiClient $ai): JsonResponse
    {
        try {
            $answer = $ai->ask((string) $request->validated('prompt'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'answer' => $answer,
        ]);
    }
}
