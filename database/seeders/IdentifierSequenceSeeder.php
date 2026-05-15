<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BarcodeFormat;
use App\Enums\IdentifierType;
use App\Models\IdentifierSequence;
use Illuminate\Database\Seeder;

final class IdentifierSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $sku = config('products.identifiers.sku', []);
        $barcode = config('products.identifiers.barcode', []);

        IdentifierSequence::query()->firstOrCreate(
            ['key' => (string) ($sku['key'] ?? 'default_sku')],
            [
                'identifier_type' => IdentifierType::Sku,
                'format' => BarcodeFormat::Internal,
                'prefix' => (string) ($sku['prefix'] ?? 'RP-'),
                'suffix' => (string) ($sku['suffix'] ?? ''),
                'pad_length' => (int) ($sku['pad_length'] ?? 6),
                'last_value' => 0,
                'is_active' => true,
            ],
        );

        IdentifierSequence::query()->firstOrCreate(
            ['key' => (string) ($barcode['key'] ?? 'default_barcode')],
            [
                'identifier_type' => IdentifierType::Barcode,
                'format' => BarcodeFormat::from((string) ($barcode['format'] ?? 'ean13')),
                'prefix' => (string) ($barcode['prefix'] ?? ''),
                'suffix' => (string) ($barcode['suffix'] ?? ''),
                'pad_length' => (int) ($barcode['pad_length'] ?? 12),
                'last_value' => 0,
                'is_active' => true,
            ],
        );
    }
}
