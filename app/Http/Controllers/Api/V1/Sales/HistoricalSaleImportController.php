<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Services\Checkout\HistoricalSaleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HistoricalSaleImportController extends Controller
{
    public function __construct(
        private readonly HistoricalSaleImportService $importer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('sales.import-historical');

        $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:10000'],
            'sales.*.branch_id' => ['required', 'integer'],
            'sales.*.sale_date' => ['required', 'string'],
            'sales.*.grand_total' => ['required', 'numeric', 'min:0'],
            'sales.*.items' => ['required', 'array', 'min:1'],
            'sales.*.items.*.product_id' => ['required', 'integer'],
            'sales.*.items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->importer->import(
            rows: $request->input('sales', []),
            importedBy: $request->user()->id,
        );

        return response()->json([
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ]);
    }
}
