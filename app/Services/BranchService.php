<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Branch\CreateBranchData;
use App\DTOs\Branch\UpdateBranchData;
use App\DTOs\User\BranchAssignmentData;
use App\DTOs\Warehouse\CreateWarehouseData;
use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Support\BranchCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BranchService
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly WarehouseService $warehouseService,
        private readonly BranchCodeGenerator $codeGenerator,
    ) {}

    public function create(CreateBranchData $data): Branch
    {
        return DB::transaction(function () use ($data) {
            $branch = $this->branches->create([
                'name' => $data->name,
                'code' => $this->codeGenerator->generate($data->name),
                'address' => $data->address,
                'currency' => $data->currency,
                'timezone' => $data->timezone,
                'operating_hours' => $data->operatingHours,
                'receipt_footer' => $data->receiptFooter,
                'is_active' => $data->isActive,
            ]);

            $this->warehouseService->create(new CreateWarehouseData(
                branchId: $branch->id,
                name: $this->resolveInitialWarehouseName($data->initialWarehouseId),
                type: WarehouseType::Backroom,
                isDefault: true,
            ));

            return $branch->load('warehouses');
        });
    }

    public function update(Branch $branch, UpdateBranchData $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $branch = $this->branches->update($branch, [
                'name' => $data->name,
                'address' => $data->address,
                'currency' => $data->currency,
                'timezone' => $data->timezone,
                'picking_strategy' => $data->pickingStrategy,
                'operating_hours' => $data->operatingHours,
                'receipt_footer' => $data->receiptFooter,
                'is_active' => $data->isActive,
                'cutover_date' => $data->cutoverDate,
            ]);

            if ($data->defaultWarehouseId !== null) {
                $belongsToBranch = $branch->warehouses()
                    ->whereKey($data->defaultWarehouseId)
                    ->exists();

                if (! $belongsToBranch) {
                    throw ValidationException::withMessages([
                        'default_warehouse_id' => __('Invalid warehouse for this branch.'),
                    ]);
                }

                $this->warehouses->setDefaultForBranch($branch->id, $data->defaultWarehouseId);
            }

            return $branch->load('warehouses');
        });
    }

    public function delete(Branch $branch): void
    {
        DB::transaction(fn () => $this->branches->delete($branch));
    }

    public function syncUserBranches(User $user, BranchAssignmentData $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $sync = [];

            foreach ($data->assignments as $assignment) {
                $sync[$assignment['branch_id']] = [
                    'is_primary' => $assignment['is_primary'],
                ];
            }

            $user->branches()->sync($sync);
        });
    }

    private function resolveInitialWarehouseName(?int $templateWarehouseId): string
    {
        if ($templateWarehouseId === null) {
            return 'Main Warehouse';
        }

        $template = $this->warehouses->findById($templateWarehouseId);

        if ($template === null || ! $template->is_active) {
            throw ValidationException::withMessages([
                'initial_warehouse_id' => __('Invalid warehouse selected.'),
            ]);
        }

        return $template->name;
    }
}
