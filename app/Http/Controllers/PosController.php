<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PosPinService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PosController extends Controller
{
    public function __construct(
        private readonly PosPinService $pinService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('POS/Index', [
            'hasPin' => $this->pinService->hasPin($user),
            'lockout' => $this->pinService->getLockoutStatus($user),
        ]);
    }
}
