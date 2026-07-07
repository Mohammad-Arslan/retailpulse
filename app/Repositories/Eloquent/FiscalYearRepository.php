<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\FiscalYear;
use App\Models\FiscalYearReopenRequest;
use App\Repositories\Contracts\FiscalYearRepositoryInterface;
use Illuminate\Support\Collection;

final class FiscalYearRepository implements FiscalYearRepositoryInterface
{
    public function allOrdered(): Collection
    {
        return FiscalYear::query()->orderByDesc('start_date')->get();
    }

    public function options(): array
    {
        return FiscalYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (FiscalYear $year) => ['id' => $year->id, 'name' => $year->name])
            ->values()
            ->all();
    }

    public function create(array $attributes): FiscalYear
    {
        return FiscalYear::query()->create($attributes);
    }

    public function update(FiscalYear $fiscalYear, array $attributes): FiscalYear
    {
        $fiscalYear->update($attributes);

        return $fiscalYear->fresh() ?? $fiscalYear;
    }

    public function pendingReopenRequests(): Collection
    {
        return FiscalYearReopenRequest::query()
            ->with(['fiscalYear:id,name', 'requestedByUser:id,name', 'firstApprovedByUser:id,name'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
    }
}
