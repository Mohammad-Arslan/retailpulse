<x-mail::message>
# {{ __('Your invoice') }}

{{ __('Invoice :number is attached. You can also view it online using the link below.', ['number' => $invoiceNumber]) }}

<x-mail::button :url="$publicUrl">
{{ __('View invoice') }}
</x-mail::button>

{{ __('Thank you for your purchase.') }}
</x-mail::message>
