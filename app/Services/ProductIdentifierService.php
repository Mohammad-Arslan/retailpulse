<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BarcodeFormat;
use App\Enums\IdentifierType;
use App\Models\IdentifierSequence;
use App\Repositories\Contracts\IdentifierSequenceRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class ProductIdentifierService
{
    public function __construct(
        private readonly IdentifierSequenceRepositoryInterface $sequences,
    ) {}

    public function nextSku(): string
    {
        return $this->next(IdentifierType::Sku, config('products.identifiers.sku.key', 'default_sku'));
    }

    public function nextBarcode(): string
    {
        return $this->next(IdentifierType::Barcode, config('products.identifiers.barcode.key', 'default_barcode'));
    }

    private function next(IdentifierType $type, string $key): string
    {
        return DB::transaction(function () use ($type, $key) {
            $sequence = $this->sequences->lockByKey($key)
                ?? $this->sequences->createFromConfig($type, $key);

            $sequence->last_value++;
            $sequence->save();

            return $this->format($sequence);
        });
    }

    private function format(IdentifierSequence $sequence): string
    {
        $number = str_pad(
            (string) $sequence->last_value,
            $sequence->pad_length,
            '0',
            STR_PAD_LEFT,
        );

        $raw = $sequence->prefix.$number.$sequence->suffix;

        return match ($sequence->format) {
            BarcodeFormat::Ean13 => $this->formatEan13($sequence->last_value),
            BarcodeFormat::Upca => $this->formatUpca($raw),
            BarcodeFormat::Code128 => strtoupper($sequence->prefix.$number),
            default => $raw,
        };
    }

    private function formatEan13(int $sequenceValue): string
    {
        $companyPrefix = preg_replace(
            '/\D/',
            '',
            (string) config('products.identifiers.barcode.ean_company_prefix', '5900000'),
        ) ?? '';

        if ($companyPrefix === '' || strlen($companyPrefix) >= 12) {
            throw new \InvalidArgumentException('EAN-13 company prefix must be between 1 and 11 digits.');
        }

        $itemLength = 12 - strlen($companyPrefix);
        $itemReference = (string) $sequenceValue;

        if (strlen($itemReference) > $itemLength) {
            throw new \OverflowException(
                "Barcode sequence exceeded capacity (max {$itemLength} digit item reference for this prefix).",
            );
        }

        $itemReference = str_pad($itemReference, $itemLength, '0', STR_PAD_LEFT);
        $body = $companyPrefix.$itemReference;

        return $body.$this->eanCheckDigit($body);
    }

    private function formatUpca(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        $body = str_pad(substr($digits, 0, 11), 11, '0', STR_PAD_LEFT);

        return $body.$this->eanCheckDigit('0'.$body);
    }

    private function eanCheckDigit(string $twelveDigits): string
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $index => $digit) {
            $weight = ($index % 2 === 0) ? 1 : 3;
            $sum += (int) $digit * $weight;
        }

        $check = (10 - ($sum % 10)) % 10;

        return (string) $check;
    }
}
