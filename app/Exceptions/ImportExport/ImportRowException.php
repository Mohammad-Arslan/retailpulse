<?php

declare(strict_types=1);

namespace App\Exceptions\ImportExport;

use RuntimeException;

final class ImportRowException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function fromValidationErrors(array $errors): self
    {
        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return new self(implode('; ', $messages));
    }
}
