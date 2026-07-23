<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Models\User;
use App\Services\Search\SearchManager;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController
{
    public function __invoke(Request $request, SearchManager $manager, BranchContext $context): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $manager->search($validated['q'], $user, $context),
        );
    }
}
