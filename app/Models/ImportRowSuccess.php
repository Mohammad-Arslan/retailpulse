<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_id',
    'row_index',
])]
class ImportRowSuccess extends Model
{
    protected $table = 'import_row_successes';

    public $timestamps = false;

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportExportJob::class, 'job_id');
    }
}
