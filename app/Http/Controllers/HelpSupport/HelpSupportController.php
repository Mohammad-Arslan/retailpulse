<?php

declare(strict_types=1);

namespace App\Http\Controllers\HelpSupport;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpSupport\AskGuideQuestionRequest;
use App\Services\HelpSupport\HelpSupportAiService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Exceptions\InsufficientCreditsException;

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
        } catch (InsufficientCreditsException) {
            return response()->json([
                'message' => 'AI Provider Has Insufficient Credits Or Quota. Please Add Credits And Try Again.',
            ], 402);
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

