<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReminderChannel;
use App\Models\ArAgingSnapshot;
use App\Models\Customer;
use App\Models\CustomerReminderLog;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendOverdueRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        /** @var list<int> $reminderDays */
        $reminderDays = SystemSetting::get('customers', 'ar_reminder_days', [7, 30, 60]);

        if ($reminderDays === []) {
            return;
        }

        $snapshotDate = now()->toDateString();
        $emailEnabled = (bool) SystemSetting::get('notifications', 'email_enabled', true);
        $smsEnabled = (bool) SystemSetting::get('notifications', 'sms_enabled', false);

        foreach ($reminderDays as $days) {
            $bucket = $this->bucketForDays((int) $days);

            if ($bucket === null) {
                continue;
            }

            ArAgingSnapshot::query()
                ->with('customer')
                ->where('snapshot_date', $snapshotDate)
                ->where($bucket, '>', 0)
                ->each(function (ArAgingSnapshot $snapshot) use ($days, $bucket, $emailEnabled, $smsEnabled) {
                    $customer = $snapshot->customer;

                    if ($customer === null) {
                        return;
                    }

                    $amountDue = (float) $snapshot->{$bucket};

                    if ($emailEnabled && $customer->email) {
                        $this->dispatchReminder($customer, ReminderChannel::Email, (string) $days, $amountDue);
                    }

                    if ($smsEnabled && $customer->phone) {
                        $this->dispatchReminder($customer, ReminderChannel::Sms, (string) $days, $amountDue);
                    }
                });
        }
    }

    private function bucketForDays(int $days): ?string
    {
        return match ($days) {
            7, 30 => 'current',
            60 => 'bucket_30',
            90 => 'bucket_60',
            120 => 'bucket_90',
            default => $days <= 30 ? 'current' : ($days <= 60 ? 'bucket_30' : ($days <= 90 ? 'bucket_60' : 'bucket_90')),
        };
    }

    private function dispatchReminder(Customer $customer, ReminderChannel $channel, string $bucket, float $amountDue): void
    {
        $alreadySent = CustomerReminderLog::query()
            ->where('customer_id', $customer->id)
            ->where('channel', $channel)
            ->where('bucket', $bucket)
            ->whereDate('sent_at', now()->toDateString())
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            Log::info('AR overdue reminder dispatched', [
                'customer_id' => $customer->id,
                'channel' => $channel->value,
                'bucket' => $bucket,
                'amount_due' => $amountDue,
            ]);

            CustomerReminderLog::query()->create([
                'customer_id' => $customer->id,
                'channel' => $channel,
                'bucket' => $bucket,
                'amount_due' => $amountDue,
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            CustomerReminderLog::query()->create([
                'customer_id' => $customer->id,
                'channel' => $channel,
                'bucket' => $bucket,
                'amount_due' => $amountDue,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'sent_at' => now(),
            ]);
        }
    }
}
