<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountingEvent;
use App\Models\AccountMapping;
use App\Models\AssetCategory;
use App\Models\BankAccount;
use App\Models\BranchAccountingProfile;
use App\Models\ChartOfAccount;
use App\Models\Cheque;
use App\Models\CostCentre;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use App\Models\PostingRuleSet;
use App\Models\TaxType;
use Illuminate\Database\Eloquent\Model;

final class AccountingAuditTypes
{
    /**
     * @var list<class-string<Model>>
     */
    private const TYPES = [
        ChartOfAccount::class,
        AccountMapping::class,
        PostingRuleSet::class,
        JournalEntry::class,
        FiscalYear::class,
        AccountingEvent::class,
        CostCentre::class,
        TaxType::class,
        CreditNote::class,
        Currency::class,
        ExchangeRate::class,
        BankAccount::class,
        BranchAccountingProfile::class,
        AssetCategory::class,
        FixedAsset::class,
        PettyCashRegister::class,
        PettyCashVoucher::class,
        Cheque::class,
    ];

    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        return self::TYPES;
    }

    public static function includes(Model $model): bool
    {
        return in_array($model::class, self::TYPES, true);
    }

    /**
     * @return list<string>
     */
    public static function classNames(): array
    {
        return self::TYPES;
    }

    public static function shortName(string $class): string
    {
        return class_basename($class);
    }
}
