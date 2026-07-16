<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\UpdateEmployeeRequest;

final readonly class UpdateEmployeeData
{
    /**
     * @param  array<string, mixed>  $employee
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $shift
     * @param  array<string, mixed>|null  $medical
     * @param  list<array<string, mixed>>  $dependents
     * @param  list<array<string, mixed>>  $bankAccounts
     * @param  list<array<string, mixed>>  $branchAssignments
     * @param  list<array{type: string, images: list<\Illuminate\Http\UploadedFile>, cnic_front: ?\Illuminate\Http\UploadedFile, cnic_back: ?\Illuminate\Http\UploadedFile}>  $imageUploads
     * @param  list<int>  $removeImageIds
     */
    public function __construct(
        public array $employee,
        public ?array $profile,
        public ?array $shift,
        public ?array $medical,
        public array $dependents,
        public array $bankAccounts,
        public array $branchAssignments,
        public ?int $holidayCalendarId,
        public bool $holidayCalendarProvided,
        public array $imageUploads,
        public array $removeImageIds,
        public array $orgEffectiveFrom = [],
    ) {}

    public static function fromRequest(UpdateEmployeeRequest $request): self
    {
        $validated = $request->validated();
        $created = CreateEmployeeData::fromValidated(
            $validated,
            CreateEmployeeData::parseImageUploads($request),
        );

        return new self(
            employee: $created->employee,
            profile: $created->profile,
            shift: $created->shift,
            medical: $created->medical,
            dependents: $created->dependents,
            bankAccounts: $created->bankAccounts,
            branchAssignments: $created->branchAssignments,
            holidayCalendarId: $created->holidayCalendarId,
            holidayCalendarProvided: array_key_exists('holiday_calendar_id', $validated),
            imageUploads: $created->imageUploads,
            removeImageIds: $created->removeImageIds,
            orgEffectiveFrom: array_filter($validated['org_effective_from'] ?? []),
        );
    }
}
