<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\ApprovalDelegation;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class ApprovalDelegationService
{
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'delegations' => $this->paginate($filters, $perPage),
            'filters' => $filters,
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'label' => "{$e->employee_code} — {$e->first_name} {$e->last_name}",
                ]),
            'scopes' => ['all', 'leave', 'expense', 'overtime'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ApprovalDelegation
    {
        $this->assertDistinctEmployees((int) $data['from_employee_id'], (int) $data['to_employee_id']);

        return ApprovalDelegation::query()->create([
            'from_employee_id' => $data['from_employee_id'],
            'to_employee_id' => $data['to_employee_id'],
            'scope' => $data['scope'] ?? 'all',
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApprovalDelegation $delegation, array $data): ApprovalDelegation
    {
        $fromId = (int) ($data['from_employee_id'] ?? $delegation->from_employee_id);
        $toId = (int) ($data['to_employee_id'] ?? $delegation->to_employee_id);
        $this->assertDistinctEmployees($fromId, $toId);

        $delegation->update([
            'from_employee_id' => $fromId,
            'to_employee_id' => $toId,
            'scope' => $data['scope'] ?? $delegation->scope,
            'effective_from' => $data['effective_from'] ?? $delegation->effective_from,
            'effective_to' => array_key_exists('effective_to', $data) ? $data['effective_to'] : $delegation->effective_to,
            'status' => $data['status'] ?? $delegation->status,
        ]);

        return $delegation->fresh(['fromEmployee', 'toEmployee']) ?? $delegation;
    }

    private function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return ApprovalDelegation::query()
            ->with([
                'fromEmployee:id,employee_code,first_name,last_name',
                'toEmployee:id,employee_code,first_name,last_name',
            ])
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters): void {
                $search = '%'.(string) $filters['search'].'%';
                $q->where(function ($inner) use ($search): void {
                    $inner->whereHas('fromEmployee', function ($employee) use ($search): void {
                        $employee->where('employee_code', 'like', $search)
                            ->orWhere('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search);
                    })->orWhereHas('toEmployee', function ($employee) use ($search): void {
                        $employee->where('employee_code', 'like', $search)
                            ->orWhere('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search);
                    });
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['scope'] ?? null, fn ($q, string $scope) => $q->where('scope', $scope))
            ->orderByDesc('effective_from')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ApprovalDelegation $d) => [
                'id' => $d->id,
                'from_employee_id' => $d->from_employee_id,
                'to_employee_id' => $d->to_employee_id,
                'from_employee' => $d->fromEmployee
                    ? "{$d->fromEmployee->employee_code} — {$d->fromEmployee->first_name} {$d->fromEmployee->last_name}"
                    : null,
                'to_employee' => $d->toEmployee
                    ? "{$d->toEmployee->employee_code} — {$d->toEmployee->first_name} {$d->toEmployee->last_name}"
                    : null,
                'scope' => $d->scope,
                'effective_from' => $d->effective_from?->toDateString(),
                'effective_to' => $d->effective_to?->toDateString(),
                'status' => $d->status,
            ]);
    }

    private function assertDistinctEmployees(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_employee_id' => __('An employee cannot delegate approvals to themselves.'),
            ]);
        }
    }
}
