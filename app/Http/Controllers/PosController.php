<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
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
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
                ->all(),
        ]);
    }
}
