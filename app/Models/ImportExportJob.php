<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'tenant_id',
    'user_id',
    'ulid',
    'type',
    'entity_type',
    'warehouse_id',
    'mode',
    'is_dry_run',
    'input_file_path',
    'output_file_path',
    'original_filename',
    'disk',
    'status',
    'total_rows',
    'processed_rows',
    'success_rows',
    'failed_rows',
    'skipped_rows',
    'summary',
    'error_message',
    'options',
    'validation_profile_id',
    'column_rules_snapshot',
    'column_mapping',
    'step',
    'file_preview',
    'queued_at',
    'started_at',
    'completed_at',
])]
class ImportExportJob extends Model
{
    protected $table = 'import_export_jobs';

    protected function casts(): array
    {
        return [
            'is_dry_run' => 'boolean',
            'summary' => 'array',
            'options' => 'array',
            'column_rules_snapshot' => 'array',
            'column_mapping' => 'array',
            'file_preview' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rowErrors(): HasMany
    {
        return $this->hasMany(ImportRowError::class, 'job_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByUlid(Builder $query, string $ulid): Builder
    {
        return $query->where('ulid', $ulid);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return $query->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId));
    }

    public function isTrayActive(): bool
    {
        if (in_array($this->status, ['validating', 'validated', 'processing', 'completing'], true)) {
            return true;
        }

        return $this->status === 'pending' && $this->queued_at !== null;
    }

    public function isWizardDraft(): bool
    {
        return $this->status === 'pending' && $this->queued_at === null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTrayActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('status', ['validating', 'validated', 'processing', 'completing'])
                ->orWhere(function (Builder $inner): void {
                    $inner->where('status', 'pending')->whereNotNull('queued_at');
                });
        });
    }

    public function markValidating(): void
    {
        $this->status = 'validating';
        $this->started_at = now();
        $this->save();
    }

    public function markValidated(): void
    {
        $this->status = 'validated';
        $this->save();
    }

    public function markProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markCompleting(): void
    {
        $this->status = 'completing';
        $this->save();
    }

    public function markCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    public function markFailed(string $message): void
    {
        $this->status = 'failed';
        $this->error_message = $message;
        $this->completed_at = now();
        $this->save();
    }

    public function incrementCounters(int $processed, int $success, int $failed, int $skipped): void
    {
        self::query()
            ->whereKey($this->id)
            ->update([
                'processed_rows' => DB::raw("processed_rows + {$processed}"),
                'success_rows' => DB::raw("success_rows + {$success}"),
                'failed_rows' => DB::raw("failed_rows + {$failed}"),
                'skipped_rows' => DB::raw("skipped_rows + {$skipped}"),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $this->refresh();

        $duration = null;
        if ($this->started_at !== null && $this->completed_at !== null) {
            $duration = $this->started_at->diffInSeconds($this->completed_at);
        }

        $summary = [
            'total' => $this->total_rows,
            'success' => $this->success_rows,
            'failed' => $this->failed_rows,
            'skipped' => $this->skipped_rows,
            'duration' => $duration,
            'is_dry_run' => $this->is_dry_run,
            'entity_type' => $this->entity_type,
            'mode' => $this->mode,
            'error_download_url' => null,
        ];

        if (filled($this->output_file_path)) {
            $summary['error_download_url'] = route('admin.import-export.errors', ['ulid' => $this->ulid]);
        }

        return $summary;
    }
}
