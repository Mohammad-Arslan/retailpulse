<?php

declare(strict_types=1);

namespace App\Http\Controllers\HelpSupport;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class HelpSupportController extends Controller
{
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

