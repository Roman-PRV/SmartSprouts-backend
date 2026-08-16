<x-mail::message>
{{ __('emails.account_deleted.greeting', ['name' => $name]) }}

{{ __('emails.account_deleted.body') }}

{{ __('emails.account_deleted.not_you') }}
</x-mail::message>
