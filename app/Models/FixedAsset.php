<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FixedAssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'asset_code',
    'name',
    'category_id',
    'acquisition_cost',
    'acquisition_date',
    'useful_life_months',
    'salvage_value',
    'depreciation_method',
    'depreciation_start_date',
    'accumulated_depreciation',
    'asset_account_id',
    'accumulated_depreciation_account_id',
    'depreciation_expense_account_id',
    'branch_id',
    'legal_entity_id',
    'location',
    'custodian_user_id',
    'status',
    'last_depreciation_date',
])]
class FixedAsset extends Model
{
    protected function casts(): array
    {
        return [
            'acquisition_cost' => 'decimal:2',
            'acquisition_date' => 'date',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'depreciation_start_date' => 'date',
            'last_depreciation_date' => 'date',
            'status' => FixedAssetStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function netBookValue(): float
    {
        return max(0, (float) $this->acquisition_cost - (float) $this->accumulated_depreciation);
    }

    public function monthlyDepreciation(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        $depreciable = (float) $this->acquisition_cost - (float) $this->salvage_value;

        return round($depreciable / $this->useful_life_months, 2);
    }
}
