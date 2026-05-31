<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutPageController extends Controller
{
    public function show(Request $request, string $cartId): Response
    {
        return Inertia::render('Checkout/Index', [
            'cartId' => $cartId,
        ]);
    }
}
