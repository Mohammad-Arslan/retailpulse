<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\DebitNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DebitNoteIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DebitNote $debitNote,
    ) {}
}
