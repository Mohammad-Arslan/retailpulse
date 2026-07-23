<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\StatutoryScheme;
use App\Models\TaxSlab;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Resolves income tax (from progressive slabs) and statutory contribution schemes
 * for a given legal entity and pay period.
 *
 * No JournalService imports — calculation only.
 */
final class StatutoryResolverService
{
    /**
     * Resolve progressive income tax for the given period gross.
     *
     * Annualises periodGross by 12/periodMonths, finds the applicable slab,
     * computes annual tax = slab.fixed_amount + marginal_rate% * (annualGross - slab.lower_bound),
     * then prorates back to the period.
     *
     * @param  string  $periodGross  bcmath string, sum of taxable earnings for the period
     * @param  int  $periodMonths  number of months this run covers (typically 1)
     */
    public function resolveTaxFromSlabs(
        string $periodGross,
        int $periodMonths,
        ?int $legalEntityId,
        CarbonImmutable $date,
    ): string {
        if ($legalEntityId === null) {
            return '0.0000';
        }

        $dateStr = $date->toDateString();

        $slabs = TaxSlab::query()
            ->where('legal_entity_id', $legalEntityId)
            ->where('status', 'active')
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateStr);
            })
            ->orderByDesc('lower_bound')
            ->get();

        if ($slabs->isEmpty()) {
            return '0.0000';
        }

        // Annualise period gross
        $annualGross = bcmul($periodGross, bcdiv((string) 12, (string) $periodMonths, 8), 4);

        // Find highest applicable slab (lower_bound <= annualGross)
        $applicableSlab = null;
        foreach ($slabs as $slab) {
            if (bccomp($annualGross, (string) $slab->lower_bound, 4) >= 0) {
                $applicableSlab = $slab;
                break;
            }
        }

        if ($applicableSlab === null) {
            return '0.0000';
        }

        $excess = bcsub($annualGross, (string) $applicableSlab->lower_bound, 4);
        $annualTax = bcadd(
            (string) $applicableSlab->fixed_amount,
            bcmul($excess, bcdiv((string) $applicableSlab->marginal_rate, '100', 8), 4),
            4,
        );

        // Prorate to period
        return bcmul($annualTax, bcdiv((string) $periodMonths, '12', 8), 4);
    }

    /**
     * Return all active statutory schemes for a legal entity effective on the given date.
     *
     * @return Collection<int, StatutoryScheme>
     */
    public function resolveStatutorySchemes(?int $legalEntityId, CarbonImmutable $date): Collection
    {
        if ($legalEntityId === null) {
            return collect();
        }

        $dateStr = $date->toDateString();

        return StatutoryScheme::query()
            ->where('legal_entity_id', $legalEntityId)
            ->where('status', 'active')
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dateStr);
            })
            ->orderBy('code')
            ->get();
    }
}
