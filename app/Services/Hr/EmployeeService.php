<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Services\Accounting\DocumentNumberService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EmployeeService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Employee::query()->with(['legalEntity', 'primaryBranch']);

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('employee_code', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['branch_id'] ?? null) !== null && $filters['branch_id'] !== '') {
            $query->where('primary_branch_id', (int) $filters['branch_id']);
        }

        $sort = in_array($filters['sort'] ?? '', ['employee_code', 'first_name', 'hire_date', 'status'], true)
            ? $filters['sort']
            : 'employee_code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $code = $this->documentNumbers->next(
                'employee',
                'EMP',
                isset($data['primary_branch_id']) ? (int) $data['primary_branch_id'] : null,
            );

            return Employee::query()->create([
                ...$data,
                'employee_code' => $code,
                'status' => $data['status'] ?? 'active',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh(['legalEntity', 'primaryBranch', 'defaultCostCentre', 'user']);
    }
}
