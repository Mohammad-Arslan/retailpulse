<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\CreateGradeData;
use App\DTOs\Hr\UpdateGradeData;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Hr\Concerns\GeneratesHrMasterCodes;
use App\Support\GradePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GradeService
{
    use GeneratesHrMasterCodes;

    private const CODE_TYPE = 'grade';

    private const CODE_PREFIX = 'GRADE';

    public function __construct(
        private readonly GradeRepositoryInterface $grades,
        private readonly DocumentNumberService $documentNumberService,
        private readonly CurrencyRepositoryInterface $currencies,
    ) {}

    protected function documentNumbers(): DocumentNumberService
    {
        return $this->documentNumberService;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     grades: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     legalEntities: Collection,
     *     currencies: list<array{id: int, code: string, name: string}>,
     *     nextCode: string
     * }
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'grades' => GradePresenter::paginated($this->paginate($filters, $perPage)),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'currencies' => $this->currencies->activeOptions(),
            'nextCode' => $this->peekMasterCode(self::CODE_TYPE, self::CODE_PREFIX),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->grades->paginate($filters, $perPage);
    }

    public function activeForSelect(): Collection
    {
        return $this->grades->activeForSelect();
    }

    public function create(CreateGradeData $data): Grade
    {
        return DB::transaction(function () use ($data): Grade {
            $attributes = $data->toArray();
            $attributes['code'] = $this->nextMasterCode(self::CODE_TYPE, self::CODE_PREFIX);
            $this->assertNoOverlappingEffectiveDates(null, $attributes);

            return $this->grades->create($attributes);
        });
    }

    public function update(Grade $grade, UpdateGradeData $data): Grade
    {
        $attributes = $data->toArray();
        $this->assertNoOverlappingEffectiveDates($grade->id, array_merge($grade->only([
            'legal_entity_id',
            'code',
            'effective_from',
            'effective_to',
        ]), $attributes));

        return $this->grades->update($grade, $attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoOverlappingEffectiveDates(?int $excludeId, array $data): void
    {
        $code = (string) ($data['code'] ?? '');
        $entityId = $data['legal_entity_id'] ?? null;
        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;

        if ($from === null || $from === '') {
            return;
        }

        $fromString = is_string($from) ? $from : (string) $from;
        $toString = $to === null || $to === '' ? null : (is_string($to) ? $to : (string) $to);

        $overlaps = $this->grades
            ->findOverlapping($code, $entityId, $fromString, $toString, $excludeId)
            ->contains(function (Grade $existing) use ($fromString, $toString): bool {
                $existingFrom = $existing->effective_from?->toDateString();
                $existingTo = $existing->effective_to?->toDateString();

                if ($existingFrom === null) {
                    return false;
                }

                $newEnd = $toString ?? '9999-12-31';
                $existingEnd = $existingTo ?? '9999-12-31';

                return $fromString <= $existingEnd && $newEnd >= $existingFrom;
            });

        if ($overlaps) {
            throw ValidationException::withMessages([
                'effective_from' => __('This grade code has overlapping effective dates for the same legal entity.'),
            ]);
        }
    }
}
