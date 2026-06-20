<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Warehouse\CreateWarehouseData;
use App\DTOs\Warehouse\UpdateWarehouseData;
use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Support\WarehouseCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WarehouseService
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly WarehouseCodeGenerator $codeGenerator,
    ) {}

    public function create(CreateWarehouseData $data): Warehouse
    {
        return DB::transaction(function () use ($data) {
            $warehouse = $this->warehouses->create([
                'branch_id' => $data->branchId,
                'name' => $data->name,
                'code' => $this->codeGenerator->generate($data->branchId, $data->name),
                'is_default' => $data->isDefault,
                'is_active' => true,
            ]);

            if ($data->isDefault) {
                $this->warehouses->setDefaultForBranch($data->branchId, $warehouse->id);
            }

            return $warehouse->fresh(['branch']);
        });
    }

    public function update(Warehouse $warehouse, UpdateWarehouseData $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data) {
            $warehouse = $this->warehouses->update($warehouse, [
                'name' => $data->name,
            ]);

            if ($data->isDefault) {
                $this->warehouses->setDefaultForBranch($warehouse->branch_id, $warehouse->id);
            } elseif ($warehouse->is_default) {
                $this->warehouses->clearDefaultForBranch($warehouse->branch_id);

                $successor = $this->warehouses->firstActiveForBranch($warehouse->branch_id);

                if ($successor !== null) {
                    $this->warehouses->setDefaultForBranch($warehouse->branch_id, $successor->id);
                }
            }

            return $warehouse->fresh(['branch']);
        });
    }

    public function deactivate(Warehouse $warehouse): Warehouse
    {
        return DB::transaction(function () use ($warehouse) {
            if (! $warehouse->is_active) {
                return $warehouse;
            }

            if ($this->warehouses->countActiveForBranch($warehouse->branch_id) <= 1) {
                throw ValidationException::withMessages([
                    'warehouse' => __('Cannot deactivate the only active warehouse for this branch.'),
                ]);
            }

            if ($this->warehouses->hasOnHandStock($warehouse)) {
                throw ValidationException::withMessages([
                    'warehouse' => __('Cannot deactivate a warehouse with stock on hand. Transfer stock first.'),
                ]);
            }

            if ($this->warehouses->hasOpenTransfers($warehouse)) {
                throw ValidationException::withMessages([
                    'warehouse' => __('Cannot deactivate a warehouse with open transfers.'),
                ]);
            }

            $wasDefault = $warehouse->is_default;

            $warehouse = $this->warehouses->update($warehouse, [
                'is_active' => false,
                'is_default' => false,
            ]);

            if ($wasDefault) {
                $successor = $this->warehouses->firstActiveForBranch($warehouse->branch_id);

                if ($successor !== null) {
                    $this->warehouses->setDefaultForBranch($warehouse->branch_id, $successor->id);
                }
            }

            return $warehouse->fresh(['branch']);
        });
    }
}
