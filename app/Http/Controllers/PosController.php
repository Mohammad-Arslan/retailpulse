<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Checkout\CheckoutConfigService;
use App\Services\Pos\PosCatalogFilterService;
use App\Services\PosPinService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PosController extends Controller
{
    public function __construct(
        private readonly PosPinService $pinService,
        private readonly CheckoutConfigService $checkoutConfig,
        private readonly PosCatalogFilterService $catalogFilters,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $branchId = app(BranchContext::class)->branchId;
        $config = $this->checkoutConfig->resolve($branchId ?? 0);

        return Inertia::render('POS/Index', [
            'hasPin' => $this->pinService->hasPin($user),
            'lockout' => $this->pinService->getLockoutStatus($user),
            'categories' => $this->catalogFilters->categoriesForBranch($branchId),
            'brands' => $this->catalogFilters->brandsForBranch($branchId),
            'posConfig' => [
                'tax_enabled' => $config['tax_enabled'],
                'tax_mode' => $config['tax_mode'],
                'default_tax_rate' => $config['default_tax_rate'],
                'currency' => $config['currency'],
                'fbr_enabled' => $config['fbr_enabled'],
            ],
        ]);
    }
}
