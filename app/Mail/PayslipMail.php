<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class PayslipMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Payslip $payslip,
        public readonly string $recipientEmail,
    ) {}

    public function envelope(): Envelope
    {
        $this->payslip->loadMissing('payrollItem.payrollRun');

        return new Envelope(
            to: [new Address($this->recipientEmail)],
            subject: __('Payslip :number', ['number' => $this->payslip->payslip_number]),
        );
    }

    public function content(): Content
    {
        $this->payslip->loadMissing(['payrollItem.employee', 'payrollItem.payrollRun']);

        return new Content(
            markdown: 'mail.payslip',
            with: [
                'payslipNumber' => $this->payslip->payslip_number,
                'employeeName' => $this->payslip->payrollItem?->employee?->fullName() ?? '',
                'periodStart' => $this->payslip->payrollItem?->payrollRun?->period_start?->toDateString(),
                'periodEnd' => $this->payslip->payrollItem?->payrollRun?->period_end?->toDateString(),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! Storage::disk($this->payslip->disk)->exists($this->payslip->path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($this->payslip->disk, $this->payslip->path)
                ->as("payslip-{$this->payslip->payslip_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
