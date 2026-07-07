<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateAssetCategoryData;
use App\DTOs\Accounting\CreateFixedAssetData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreAssetCategoryRequest;
use App\Http\Requests\Admin\Accounting\StoreFixedAssetRequest;
use App\Models\FixedAsset;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $fixedAssetService,
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
}
