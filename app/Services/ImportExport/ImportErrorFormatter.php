<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Models\ImportExportJob;
use Illuminate\Support\Str;

final class ImportErrorFormatter
{
    /**
     * @param  list<array<string, mixed>>  $columnRules
     */
    public function __construct(
        private readonly array $columnRules = [],
    ) {}

    public static function forJob(ImportExportJob $job): self
    {
        return new self($job->column_rules_snapshot ?? []);
    }

    public function fieldLabel(string $field): string
    {
        if ($field === '_row') {
            return 'Row';
        }

        foreach ($this->columnRules as $column) {
            $columnKey = (string) ($column['column_key'] ?? '');
            $mappedTo = (string) ($column['mapped_to'] ?? $columnKey);

            if ($field === $columnKey || $field === $mappedTo) {
                $label = trim((string) ($column['display_label'] ?? ''));

                return $label !== '' ? $label : $this->humanize($field);
            }
        }

        return $this->humanize($field);
    }

    public function formatMessage(string $field, string $message, mixed $value = null): string
    {
        $label = $this->fieldLabel($field);
        $valueText = $this->formatValue($value);
        $lower = mb_strtolower($message);

        if ($this->looksUserFriendly($message, $field)) {
            return $message;
        }

        if (str_contains($lower, 'is invalid') || str_contains($lower, 'selected') && str_contains($lower, 'invalid')) {
            if ($valueText !== '—') {
                return "{$label} \"{$valueText}\" was not found or is not allowed.";
            }

            return "The {$label} value was not found or is not allowed.";
        }

        if (str_contains($lower, 'is required') || str_contains($lower, 'must be present')) {
            return "{$label} is required but was empty.";
        }

        if (str_contains($lower, 'has already been taken') || str_contains($lower, 'already exists')) {
            if ($valueText !== '—') {
                return "{$label} \"{$valueText}\" already exists.";
            }

            return "This {$label} already exists.";
        }

        if (str_contains($lower, 'must be a number') || str_contains($lower, 'must be numeric')) {
            return "{$label} must be a number.";
        }

        if (str_contains($lower, 'must be a string')) {
            return "{$label} must be text.";
        }

        if (str_contains($lower, 'must be a valid email')) {
            return "{$label} must be a valid email address.";
        }

        if (str_contains($lower, 'must be true or false') || str_contains($lower, 'must be a boolean')) {
            return "{$label} must be yes/no or true/false.";
        }

        if (str_contains($lower, 'format is invalid') || str_contains($lower, 'does not match')) {
            return "{$label} has an invalid format.";
        }

        if (str_contains($lower, 'must not be greater than') || str_contains($lower, 'may not be greater than')) {
            return "{$label} is too long or too large.";
        }

        if (str_contains($lower, 'must be at least') || str_contains($lower, 'must be greater than')) {
            return "{$label} is too short or too small.";
        }

        return $this->replaceTechnicalTokens($message, $field, $label);
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->columnRules as $column) {
            $field = (string) ($column['column_key'] ?? '');

            if ($field === '') {
                continue;
            }

            $attributes["rows.*.{$field}"] = $this->fieldLabel($field);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        $messages = [];

        foreach ($this->columnRules as $column) {
            $field = (string) ($column['column_key'] ?? '');

            if ($field === '') {
                continue;
            }

            $label = $this->fieldLabel($field);
            $prefix = "rows.*.{$field}";

            $messages["{$prefix}.required"] = "{$label} is required.";
            $messages["{$prefix}.exists"] = "The {$label} value was not found.";
            $messages["{$prefix}.unique"] = "This {$label} already exists.";
            $messages["{$prefix}.email"] = "{$label} must be a valid email address.";
            $messages["{$prefix}.numeric"] = "{$label} must be a number.";
            $messages["{$prefix}.string"] = "{$label} must be text.";
            $messages["{$prefix}.boolean"] = "{$label} must be yes/no or true/false.";
            $messages["{$prefix}.regex"] = "{$label} has an invalid format.";
            $messages["{$prefix}.date"] = "{$label} must be a valid date.";
            $messages["{$prefix}.in"] = "The {$label} value is not allowed.";
        }

        return $messages;
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $rowData
     * @return array<string, list<string>>
     */
    public function formatErrors(array $errors, array $rowData = []): array
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            $formatted[$field] = array_map(
                fn (mixed $message): string => $this->formatMessage(
                    (string) $field,
                    (string) $message,
                    $rowData[$field] ?? null,
                ),
                (array) $messages,
            );
        }

        return $formatted;
    }

    private function looksUserFriendly(string $message, string $field): bool
    {
        if (str_contains($message, 'rows.') || str_contains($message, 'rows.*.')) {
            return false;
        }

        if (preg_match('/\brows\.\d+\./', $message)) {
            return false;
        }

        if (str_contains($message, 'The selected ') && str_contains($message, ' is invalid')) {
            return false;
        }

        $label = $this->fieldLabel($field);

        return ! str_contains($message, $field) || str_contains($message, $label);
    }

    private function replaceTechnicalTokens(string $message, string $field, string $label): string
    {
        $message = preg_replace('/\brows\.\d+\./', '', $message) ?? $message;
        $message = preg_replace('/\brows\.\*\./', '', $message) ?? $message;
        $message = preg_replace('/^The selected\s+/i', 'The ', $message) ?? $message;
        $message = str_replace($field, $label, $message);

        return trim($message);
    }

    private function humanize(string $field): string
    {
        return Str::of($field)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
