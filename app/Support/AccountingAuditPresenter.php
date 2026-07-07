<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\CostCentre;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PostingRuleSet;
use Illuminate\Database\Eloquent\Model;

final class AccountingAuditPresenter
{
    /**
     * @param  iterable<int, AuditLog>  $logs
     * @return array<string, array<int, string>>
     */
    public static function entityLabels(iterable $logs): array
    {
        $byType = [];

        foreach ($logs as $log) {
            if ($log->auditable_type && $log->auditable_id) {
                $byType[$log->auditable_type][] = (int) $log->auditable_id;
            }
        }

        $labels = [];

        foreach ($byType as $type => $ids) {
            if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            $uniqueIds = array_values(array_unique($ids));
            $models = $type::query()->whereIn('id', $uniqueIds)->get()->keyBy('id');

            foreach ($uniqueIds as $id) {
                $model = $models->get($id);
                $labels[$type][$id] = $model
                    ? self::labelForModel($model)
                    : '#'.$id;
            }
        }

        return $labels;
    }

    public static function labelForModel(Model $model): string
    {
        return match (true) {
            $model instanceof JournalEntry => (string) ($model->journal_number ?? 'Journal #'.$model->getKey()),
            $model instanceof ChartOfAccount => trim(($model->code ?? '').' '.($model->name ?? '')),
            $model instanceof AccountMapping => (string) ($model->mapping_key ?? 'Mapping #'.$model->getKey()),
            $model instanceof PostingRuleSet => (string) ($model->name ?? 'Rule #'.$model->getKey()),
            $model instanceof FiscalYear => (string) ($model->name ?? 'FY #'.$model->getKey()),
            $model instanceof CostCentre => trim(($model->code ?? '').' '.($model->name ?? '')),
            $model instanceof CreditNote => (string) ($model->credit_note_number ?? 'Credit Note #'.$model->getKey()),
            isset($model->code, $model->name) => trim($model->code.' '.$model->name),
            isset($model->name) => (string) $model->name,
            isset($model->code) => (string) $model->code,
            default => class_basename($model).' #'.$model->getKey(),
        };
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function changesSummary(string $event, ?array $old, ?array $new): string
    {
        $hidden = ['password', 'remember_token', 'updated_at', 'created_at'];

        if ($event === 'created') {
            $fields = array_keys(array_diff_key($new ?? [], array_flip($hidden)));

            return $fields === []
                ? 'Record created'
                : 'Created: '.self::formatFieldList($fields);
        }

        if ($event === 'deleted') {
            return 'Record deleted';
        }

        $parts = [];

        foreach ($new ?? [] as $field => $value) {
            if (in_array($field, $hidden, true)) {
                continue;
            }

            $previous = $old[$field] ?? null;

            if ($previous == $value) {
                continue;
            }

            $parts[] = $field.': '.self::formatValue($previous).' → '.self::formatValue($value);
        }

        if ($parts === []) {
            return 'Updated';
        }

        if (count($parts) > 3) {
            return implode('; ', array_slice($parts, 0, 3)).'…';
        }

        return implode('; ', $parts);
    }

    public static function entityRouteKey(Model $model): ?string
    {
        return match (true) {
            $model instanceof JournalEntry => 'admin.accounting.journal-entries.show',
            $model instanceof ChartOfAccount => null,
            default => null,
        };
    }

    /**
     * @param  list<string>  $fields
     */
    private static function formatFieldList(array $fields): string
    {
        if (count($fields) > 4) {
            return implode(', ', array_slice($fields, 0, 4)).'…';
        }

        return implode(', ', $fields);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        $string = (string) $value;

        return strlen($string) > 40 ? substr($string, 0, 37).'…' : $string;
    }
}
