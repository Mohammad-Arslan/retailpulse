<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use App\Models\CreditNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CreditNoteIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CreditNote $creditNote,
    ) {}
}
