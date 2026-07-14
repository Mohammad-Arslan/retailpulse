<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateAssetCategoryData;
use App\DTOs\Accounting\CreateFixedAssetData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreAssetCategoryRequest;
use App\Http\Requests\Admin\Accounting\StoreAssetDisposalRequest;
use App\Http\Requests\Admin\Accounting\StoreFixedAssetRequest;
use App\Models\FixedAsset;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\AssetDisposalService;
use App\Services\Accounting\FixedAssetService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $fixedAssetService,
        private readonly AssetDisposalService $assetDisposalService,
        private readonly AssetDepreciationService $assetDepreciationService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', FixedAsset::class);

        return Inertia::render('Admin/Accounting/FixedAssets/Index', $this->fixedAssetService->indexPayload());
    }

    public function store(StoreFixedAssetRequest $request): RedirectResponse
    {
        $this->authorize('create', FixedAsset::class);

        $this->fixedAssetService->create(CreateFixedAssetData::fromRequest($request));

        return back()->with('success', __('Fixed asset created successfully.'));
    }

    public function storeCategory(StoreAssetCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', FixedAsset::class);

        $this->fixedAssetService->createCategory(CreateAssetCategoryData::fromRequest($request));

        return back()->with('success', __('Asset category created successfully.'));
    }

    public function dispose(StoreAssetDisposalRequest $request, FixedAsset $fixedAsset): RedirectResponse
    {
        $this->authorize('dispose', $fixedAsset);

        try {
            $this->assetDisposalService->dispose(
                $fixedAsset,
                Carbon::parse($request->validated('disposal_date')),
                (float) $request->validated('proceeds_amount'),
                (int) $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Fixed asset disposed successfully.'));
    }

    public function runDepreciation(Request $request): RedirectResponse
    {
        $this->authorize('runDepreciation', FixedAsset::class);

        $asOf = $request->input('date') ?: now()->toDateString();
        $processed = $this->assetDepreciationService->processMonthly($asOf);

        return back()->with(
            'success',
            __('Processed depreciation for :count asset(s) as of :date.', [
                'count' => $processed->count(),
                'date' => $asOf,
            ]),
        );
    }
}
