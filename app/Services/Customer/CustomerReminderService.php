<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\ArLedgerEntryType;
use App\Enums\ReminderChannel;
use App\Models\ArAgingSnapshot;
use App\Models\Customer;
use App\Models\CustomerArLedger;
use App\Models\CustomerReminderLog;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class CustomerReminderService
{
    public function __construct(
        private readonly ArAgingService $aging,
    ) {}

    /**
     * @return list<CustomerReminderLog>
     */
    public function sendOverdueReminders(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $thresholds = SystemSetting::get('customers', 'ar_reminder_days', [7, 30, 60]);

        if (! is_array($thresholds) || $thresholds === []) {
            return [];
        }

        $this->aging->buildSnapshotsForDate($asOf);

        $logs = [];

        foreach ($thresholds as $days) {
            $days = (int) $days;

            if ($days <= 0) {
                continue;
            }

            $bucket = $this->bucketForThreshold($days);
            $customers = $this->customersDueForReminder($asOf, $bucket, $days);

            foreach ($customers as $row) {
                $customer = Customer::query()->find($row['customer_id']);

                if ($customer === null) {
                    continue;
                }

                if ($this->alreadyRemindedToday($customer->id, $bucket)) {
                    continue;
                }

                foreach ($this->enabledChannels() as $channel) {
                    $logs[] = $this->dispatchReminder(
                        customer: $customer,
                        channel: $channel,
                        bucket: $bucket,
                        amountDue: (float) $row['amount_due'],
                        daysOverdue: $days,
                    );
                }
            }
        }

        return $logs;
    }

    /**
     * @return list<ReminderChannel>
     */
    private function enabledChannels(): array
    {
        $channels = [];

        if (SystemSetting::get('notifications', 'email_enabled', true)) {
            $channels[] = ReminderChannel::Email;
        }

        if (SystemSetting::get('notifications', 'sms_enabled', false)) {
            $channels[] = ReminderChannel::Sms;
        }

        return $channels;
    }

    /**
     * @return list<array{customer_id: int, amount_due: float}>
     */
    private function customersDueForReminder(Carbon $asOf, string $bucket, int $days): array
    {
        $snapshots = ArAgingSnapshot::query()
            ->whereDate('snapshot_date', $asOf->toDateString())
            ->where($bucket, '>', 0)
            ->get();

        $results = [];

        foreach ($snapshots as $snapshot) {
            $hasOverdueInvoice = CustomerArLedger::query()
                ->where('customer_id', $snapshot->customer_id)
                ->where('branch_id', $snapshot->branch_id)
                ->where('entry_type', ArLedgerEntryType::Invoice)
                ->whereDate('created_at', '<=', $asOf->copy()->subDays($days)->toDateString())
                ->exists();

            if (! $hasOverdueInvoice) {
                continue;
            }

            $results[] = [
                'customer_id' => $snapshot->customer_id,
                'amount_due' => (float) $snapshot->{$bucket},
            ];
        }

        /** @var Collection<int, array{customer_id: int, amount_due: float}> $grouped */
        $grouped = collect($results)
            ->groupBy('customer_id')
            ->map(fn (Collection $rows) => [
                'customer_id' => (int) $rows->first()['customer_id'],
                'amount_due' => $rows->sum('amount_due'),
            ])
            ->values()
            ->all();

        return $grouped;
    }

    private function dispatchReminder(
        Customer $customer,
        ReminderChannel $channel,
        string $bucket,
        float $amountDue,
        int $daysOverdue,
    ): CustomerReminderLog {
        $status = 'sent';
        $error = null;

        try {
            match ($channel) {
                ReminderChannel::Email => $this->sendEmailReminder($customer, $amountDue, $daysOverdue),
                ReminderChannel::Sms => $this->sendSmsReminder($customer, $amountDue, $daysOverdue),
                ReminderChannel::Whatsapp => $this->sendWhatsappReminder($customer, $amountDue, $daysOverdue),
            };
        } catch (\Throwable $exception) {
            $status = 'failed';
            $error = $exception->getMessage();
            Log::warning('Customer overdue reminder failed', [
                'customer_id' => $customer->id,
                'channel' => $channel->value,
                'error' => $error,
            ]);
        }

        return CustomerReminderLog::query()->create([
            'customer_id' => $customer->id,
            'channel' => $channel,
            'bucket' => $bucket,
            'amount_due' => $amountDue,
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }

    private function sendEmailReminder(Customer $customer, float $amountDue, int $daysOverdue): void
    {
        if ($customer->email === null || $customer->email === '') {
            throw new \RuntimeException('Customer has no email address.');
        }

        Mail::raw(
            __('Your account has an overdue balance of :amount (over :days days). Please contact us to arrange payment.', [
                'amount' => number_format($amountDue, 2),
                'days' => $daysOverdue,
            ]),
            fn ($message) => $message
                ->to($customer->email)
                ->subject(__('Payment reminder'))
        );
    }

    private function sendSmsReminder(Customer $customer, float $amountDue, int $daysOverdue): void
    {
        if ($customer->phone === null || $customer->phone === '') {
            throw new \RuntimeException('Customer has no phone number.');
        }

        Log::info('Customer SMS reminder queued (stub)', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'amount' => $amountDue,
            'days' => $daysOverdue,
        ]);
    }

    private function sendWhatsappReminder(Customer $customer, float $amountDue, int $daysOverdue): void
    {
        Log::info('Customer WhatsApp reminder queued (stub)', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'amount' => $amountDue,
            'days' => $daysOverdue,
        ]);
    }

    private function bucketForThreshold(int $days): string
    {
        return match (true) {
            $days <= 30 => 'bucket_30',
            $days <= 60 => 'bucket_60',
            default => 'bucket_over_90',
        };
    }

    private function alreadyRemindedToday(int $customerId, string $bucket): bool
    {
        return CustomerReminderLog::query()
            ->where('customer_id', $customerId)
            ->where('bucket', $bucket)
            ->whereDate('sent_at', today())
            ->exists();
    }
}
