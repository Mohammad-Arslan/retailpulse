<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Unit\CreateUnitData;
use App\DTOs\Unit\UpdateUnitData;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Support\UnitAbbreviation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UnitService
{
    public function __construct(
        private readonly UnitRepositoryInterface $units,
    ) {}

    public function create(CreateUnitData $data): Unit
    {
        return DB::transaction(function () use ($data) {
            $unit = new Unit(['name' => $data->name]);

            return $this->units->create([
                'name' => $data->name,
                'abbreviation' => UnitAbbreviation::forModel($unit, $data->name),
                'is_active' => $data->isActive,
            ]);
        });
    }

    public function update(Unit $unit, UpdateUnitData $data): Unit
    {
        return DB::transaction(fn () => $this->units->update($unit, [
            'name' => $data->name,
            'abbreviation' => UnitAbbreviation::forModel($unit, $data->name),
            'is_active' => $data->isActive,
        ]));
    }

    public function delete(Unit $unit): void
    {
        DB::transaction(function () use ($unit) {
            if ($unit->products()->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('Cannot delete a unit that is assigned to products.'),
                ]);
            }

            $this->units->delete($unit);
        });
    }
}
