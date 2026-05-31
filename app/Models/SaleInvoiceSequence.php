<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoiceSequence extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'branch_id',
        'date',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'last_sequence' => 'integer',
        ];
    }
}
