<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FbrInvoiceStatus;
use App\Models\FbrInvoiceQueue;
use App\Models\SaleInvoice;
use App\Models\SystemSetting;
use App\Services\Checkout\FbrReportingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SubmitFbrInvoiceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $saleInvoiceId,
    ) {}

    public function handle(FbrReportingService $fbr): void
    {
        $invoice = SaleInvoice::query()->with('sale')->find($this->saleInvoiceId);

        if ($invoice === null || $invoice->fbr_status === FbrInvoiceStatus::Submitted) {
            return;
        }

        $queue = FbrInvoiceQueue::query()
            ->where('sale_invoice_id', $invoice->id)
            ->first();

        if ($queue === null) {
            return;
        }

        $maxAttempts = (int) SystemSetting::get('fbr', 'retry_max_attempts', 3);
        $backoffSec = (int) SystemSetting::get('fbr', 'retry_backoff_sec', 60);

        $result = $fbr->submit($invoice->sale);
        $queue->attempts = $queue->attempts + 1;
        $queue->last_attempted_at = now();

        if ($result['success']) {
            $queue->status = 'submitted';
            $queue->save();

            $invoice->update([
                'fbr_status' => FbrInvoiceStatus::Submitted,
                'fbr_invoice_number' => $result['invoice_number'],
            ]);

            return;
        }

        $queue->last_error = $result['error'];

        if ($queue->attempts >= $maxAttempts) {
            $queue->status = 'failed';
            $queue->save();
            $invoice->update(['fbr_status' => FbrInvoiceStatus::Failed]);
            Log::warning('FBR invoice submission failed after max attempts.', [
                'sale_invoice_id' => $invoice->id,
                'error' => $result['error'],
            ]);

            return;
        }

        $queue->next_attempt_at = now()->addSeconds($backoffSec * $queue->attempts);
        $queue->save();

        self::dispatch($invoice->id)->delay($queue->next_attempt_at);
    }
}
