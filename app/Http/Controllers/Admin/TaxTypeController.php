<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateTaxTypeData;
use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreTaxTypeRequest;
use App\Models\TaxType;
use App\Services\Accounting\TaxTypeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TaxTypeController extends Controller
{
    public function __construct(
        private readonly TaxTypeService $taxTypeService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', TaxType::class);

        return Inertia::render('Admin/Accounting/TaxTypes/Index', [
            ...$this->taxTypeService->indexPayload(),
            'taxDirections' => TaxDirection::values(),
            'calculationMethods' => TaxCalculationMethod::values(),
        ]);
    }

    public function store(StoreTaxTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', TaxType::class);

        $this->taxTypeService->create(CreateTaxTypeData::fromRequest($request));

        return back()->with('success', __('Tax type created successfully.'));
    }
}
