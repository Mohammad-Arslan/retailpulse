<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Checkout\CheckoutConfigService;
use App\Services\PosPinService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PosController extends Controller
{
    public function __construct(
        private readonly PosPinService $pinService,
        private readonly CheckoutConfigService $checkoutConfig,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $branchId = app(\App\Support\BranchContext::class)->branchId ?? 0;
        $config = $this->checkoutConfig->resolve($branchId);

        return Inertia::render('POS/Index', [
            'hasPin' => $this->pinService->hasPin($user),
            'lockout' => $this->pinService->getLockoutStatus($user),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
                ->all(),
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
