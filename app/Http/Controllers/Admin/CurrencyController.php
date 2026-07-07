<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateCurrencyData;
use App\DTOs\Accounting\CreateExchangeRateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreCurrencyRequest;
use App\Http\Requests\Admin\Accounting\StoreExchangeRateRequest;
use App\Models\Currency;
use App\Services\Accounting\CurrencyAdminService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyAdminService $currencyAdminService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Currency::class);

        return Inertia::render('Admin/Accounting/Currencies/Index', $this->currencyAdminService->indexPayload());
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->authorize('create', Currency::class);

        $this->currencyAdminService->create(CreateCurrencyData::fromRequest($request));

        return back()->with('success', __('Currency created successfully.'));
    }

    public function storeRate(StoreExchangeRateRequest $request): RedirectResponse
    {
        $this->authorize('create', Currency::class);

        $this->currencyAdminService->storeRate(
            CreateExchangeRateData::fromRequest($request),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Exchange rate saved successfully.'));
    }
}
