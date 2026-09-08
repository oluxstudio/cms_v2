<x-mail::message>
# You're on the team 🎉

@if ($inviterName)
**{{ $inviterName }}** has added you to **{{ $siteName }}** on {{ config('app.name') }} as **{{ $roleName }}**.
@else
You've been added to **{{ $siteName }}** on {{ config('app.name') }} as **{{ $roleName }}**.
@endif

@if ($tempPassword)
An account is ready for you — just log in:

<x-mail::panel>
**Email:** {{ $email }}<br>
**Temporary password:** {{ $tempPassword }}
</x-mail::panel>

For your security, change this password from **Settings** after your first login.
@else
Log in with your existing {{ config('app.name') }} account ({{ $email }}) — {{ $siteName }} will be waiting on your dashboard.
@endif

<x-mail::button :url="$loginUrl">
Log in
</x-mail::button>

What you can see and do on {{ $siteName }} is defined by the **{{ $roleName }}** role. If you weren't expecting this, contact the person who added you.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
