<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\BarcodeFormat;
use App\Enums\IdentifierType;
use App\Models\IdentifierSequence;
use App\Repositories\Contracts\IdentifierSequenceRepositoryInterface;

final class IdentifierSequenceRepository implements IdentifierSequenceRepositoryInterface
{
    public function lockByKey(string $key): ?IdentifierSequence
    {
        return IdentifierSequence::query()
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
    }

    public function createFromConfig(IdentifierType $type, string $key): IdentifierSequence
    {
        $configKey = $type === IdentifierType::Sku ? 'sku' : 'barcode';
        $config = config("products.identifiers.{$configKey}", []);

        return IdentifierSequence::query()->create([
            'key' => $key,
            'identifier_type' => $type,
            'format' => BarcodeFormat::from((string) ($config['format'] ?? 'internal')),
            'prefix' => (string) ($config['prefix'] ?? ''),
            'suffix' => (string) ($config['suffix'] ?? ''),
            'pad_length' => (int) ($config['pad_length'] ?? 6),
            'last_value' => 0,
            'is_active' => true,
        ]);
    }
}
