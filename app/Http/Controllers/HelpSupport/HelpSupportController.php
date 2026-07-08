<?php

declare(strict_types=1);

namespace App\Http\Controllers\HelpSupport;

use App\Exceptions\HelpSupport\UnknownGuideException;
use App\Http\Controllers\Controller;
use App\Http\Requests\HelpSupport\AskGuideQuestionRequest;
use App\Services\HelpSupport\HelpSupportAiService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Throwable;

final class HelpSupportController extends Controller
{
    public function ask(string $guide, AskGuideQuestionRequest $request, HelpSupportAiService $ai): Responsable|JsonResponse
    {
        try {
            return $ai->streamGuideAnswer(
                guide: $guide,
                message: (string) $request->validated('message'),
                history: $request->validated('history'),
            );
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 402);
        } catch (ConnectionException $e) {
            $url = (string) data_get(config('ai.providers.'.config('ai.default')), 'url', 'http://127.0.0.1:11434');

            return response()->json([
                'message' => 'Could not connect to the AI provider. Ensure Ollama is running at '.$url.'.',
            ], 503);
        } catch (RequestException $e) {
            $status = $e->response?->status() ?? 502;
            $body = (string) ($e->response?->body() ?? '');
            $lower = strtolower($body);
            $model = (string) data_get(
                config('ai.providers.'.config('ai.default')),
                'models.text.default',
                env('OLLAMA_MODEL', 'qwen2.5-coder:7b'),
            );

            if ($status === 401) {
                return response()->json([
                    'message' => 'AI provider authentication failed. Check API key / credentials in .env.',
                ], 401);
            }

            if ($status === 404 || str_contains($lower, 'not found') || str_contains($lower, 'model')) {
                return response()->json([
                    'message' => "AI model [{$model}] was not found. Run: ollama pull {$model}",
                ], 404);
            }

            return response()->json([
                'message' => 'AI request failed'.($status ? " (HTTP {$status})" : '').'.',
            ], $status >= 400 && $status < 600 ? $status : 502);
        } catch (UnknownGuideException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'AI request failed: '.$e->getMessage(),
            ], 503);
        }
    }

    public function index(): Response
    {
        return Inertia::render('HelpSupport/Index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['label' => 'Help & Support'],
            ],
        ]);
    }

    public function accountingGuide(): Response
    {
        return Inertia::render('HelpSupport/Guides/Accounting');
    }

    public function customersLoyaltyGuide(): Response
    {
        return Inertia::render('HelpSupport/Guides/CustomersLoyalty');
    }

    public function inventoryCatalogueGuide(): Response
    {
        return Inertia::render('HelpSupport/Guides/InventoryCatalogue');
    }

    public function putProductInStockGuide(): Response
    {
        return Inertia::render('HelpSupport/Guides/PutProductInStock');
    }
}
