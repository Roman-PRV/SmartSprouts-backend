<x-mail::message>
{{ __('emails.deletion_code.intro') }}

<x-mail::panel>
{{ $code }}
</x-mail::panel>

{{ __('emails.deletion_code.expires', ['minutes' => $minutes]) }}

{{ __('emails.deletion_code.ignore') }}
</x-mail::message>
