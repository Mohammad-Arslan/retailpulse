<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SaleInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class SaleInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly SaleInvoice $invoice,
        public readonly string $publicUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invoice :number', ['number' => $this->invoice->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sale-invoice',
            with: [
                'invoiceNumber' => $this->invoice->number,
                'publicUrl' => $this->publicUrl,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoice->pdf_path === null || ! Storage::disk('local')->exists($this->invoice->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->invoice->pdf_path)
                ->as("invoice-{$this->invoice->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
