<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\DTOs\Payroll\CreateSalaryStructureData;
use App\DTOs\Payroll\UpdateSalaryStructureData;
use App\Models\SalaryStructure;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Concerns\GeneratesMasterCodes;
use Illuminate\Support\Facades\DB;

final class SalaryStructureService
{
    use GeneratesMasterCodes;

    private const CODE_TYPE = 'salary_structure';

    private const CODE_PREFIX = 'SALSTR';

    public function __construct(
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    protected function documentNumbers(): DocumentNumberService
    {
        return $this->documentNumberService;
    }

    public function nextCode(): string
    {
        return $this->peekMasterCode(self::CODE_TYPE, self::CODE_PREFIX);
    }

    public function create(CreateSalaryStructureData $data): SalaryStructure
    {
        return DB::transaction(function () use ($data): SalaryStructure {
            $structure = SalaryStructure::query()->create([
                ...$data->toArray(),
                'code' => $this->nextMasterCode(self::CODE_TYPE, self::CODE_PREFIX),
            ]);

            $this->syncComponents($structure, $data->components);

            return $structure->fresh(['components.component', 'legalEntity']);
        });
    }

    public function update(SalaryStructure $structure, UpdateSalaryStructureData $data): SalaryStructure
    {
        return DB::transaction(function () use ($structure, $data): SalaryStructure {
            if ($data->attributes !== []) {
                $structure->update($data->attributes);
            }

            $this->syncComponents($structure, $data->components);

            return $structure->fresh(['components.component', 'legalEntity']);
        });
    }

    /**
     * @param  list<array{pay_component_id: int, amount_or_rate: float|null, sequence: int|null}>  $components
     */
    private function syncComponents(SalaryStructure $structure, array $components): void
    {
        $structure->components()->delete();

        foreach ($components as $index => $component) {
            $structure->components()->create([
                'pay_component_id' => $component['pay_component_id'],
                'amount_or_rate' => $component['amount_or_rate'],
                'sequence' => $component['sequence'] ?? ($index + 1) * 10,
            ]);
        }
    }
}
