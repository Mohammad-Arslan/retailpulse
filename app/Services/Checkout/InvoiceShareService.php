<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Mail\SaleInvoiceMail;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class InvoiceShareService
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
    ) {}

    /**
     * @return array{method: string, public_url: string, pdf_path: string|null, message?: string}
     */
    public function share(Sale $sale, SaleInvoice $invoice, string $method): array
    {
        $enabled = $this->enabledMethods();

        if (! in_array($method, $enabled, true)) {
            throw ValidationException::withMessages([
                'method' => __('This share method is not enabled.'),
            ]);
        }

        if ($invoice->pdf_path === null) {
            $this->pdf->generate($invoice);
            $invoice->refresh();
        }

        $publicUrl = route('invoice.public', $invoice->public_token);

        return match ($method) {
            'email' => $this->shareByEmail($sale, $invoice, $publicUrl),
            'whatsapp' => $this->shareByWhatsApp($sale, $invoice, $publicUrl),
            'link', 'print' => [
                'method' => $method,
                'public_url' => $publicUrl,
                'pdf_path' => $invoice->pdf_path,
            ],
            default => throw ValidationException::withMessages([
                'method' => __('Unsupported share method.'),
            ]),
        };
    }

    /**
     * @return list<string>
     */
    public function enabledMethods(): array
    {
        $methods = [];

        if ((bool) SystemSetting::get('checkout', 'invoice_share_email', true)) {
            $methods[] = 'email';
        }
        if ((bool) SystemSetting::get('checkout', 'invoice_share_link', true)) {
            $methods[] = 'link';
        }
        if ((bool) SystemSetting::get('checkout', 'invoice_share_whatsapp', false)) {
            $methods[] = 'whatsapp';
        }
        if ((bool) SystemSetting::get('checkout', 'invoice_share_print', true)) {
            $methods[] = 'print';
        }

        /** @var list<string>|null $legacy */
        $legacy = SystemSetting::get('checkout', 'invoice_share_methods', null);
        if ($legacy !== null && is_array($legacy) && $methods === []) {
            return array_values($legacy);
        }

        return $methods !== [] ? $methods : ['email', 'link', 'print'];
    }

    /**
     * @return array{method: string, public_url: string, pdf_path: string|null, message: string}
     */
    private function shareByEmail(Sale $sale, SaleInvoice $invoice, string $publicUrl): array
    {
        $sale->loadMissing('customer');
        $email = $sale->customer?->email;

        if ($email === null || trim($email) === '') {
            throw ValidationException::withMessages([
                'customer' => __('A customer with an email address is required to send the invoice.'),
            ]);
        }

        Mail::to($email)->send(new SaleInvoiceMail($invoice, $publicUrl));

        $invoice->update(['emailed_at' => now()]);

        return [
            'method' => 'email',
            'public_url' => $publicUrl,
            'pdf_path' => $invoice->pdf_path,
            'message' => __('Invoice emailed to :email.', ['email' => $email]),
        ];
    }

    /**
     * @return array{method: string, public_url: string, pdf_path: string|null, message: string}
     */
    private function shareByWhatsApp(Sale $sale, SaleInvoice $invoice, string $publicUrl): array
    {
        $sale->loadMissing('customer');
        $phone = $sale->customer?->phone;
        $apiUrl = (string) SystemSetting::get('checkout', 'whatsapp_api_url', '');

        if ($phone === null || trim($phone) === '') {
            throw ValidationException::withMessages([
                'customer' => __('A customer with a phone number is required for WhatsApp sharing.'),
            ]);
        }

        if ($apiUrl !== '') {
            Http::timeout(10)->post($apiUrl, [
                'to' => $phone,
                'message' => __('Your invoice :number: :url', [
                    'number' => $invoice->number,
                    'url' => $publicUrl,
                ]),
            ]);
        } else {
            Log::info('WhatsApp invoice share (stub)', [
                'sale_id' => $sale->id,
                'invoice_id' => $invoice->id,
                'phone' => $phone,
                'url' => $publicUrl,
            ]);
        }

        return [
            'method' => 'whatsapp',
            'public_url' => $publicUrl,
            'pdf_path' => $invoice->pdf_path,
            'message' => __('WhatsApp message queued for :phone.', ['phone' => $phone]),
        ];
    }
}
