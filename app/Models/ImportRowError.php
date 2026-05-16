<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_id',
    'row_index',
    'row_data',
    'errors',
])]
class ImportRowError extends Model
{
    protected $table = 'import_row_errors';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'errors' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportExportJob::class, 'job_id');
    }
}
