<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Storage\FileStorageDiskRegistrar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'document_type',
    'original_name',
    'disk',
    'path',
    'mime_type',
    'size_bytes',
    'uploaded_by',
])]
class EmployeeAttachment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function absolutePath(): string
    {
        return app(FileStorageDiskRegistrar::class)->resolve($this->disk)->path($this->path);
    }
}
