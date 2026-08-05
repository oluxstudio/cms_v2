<x-mail::message>
# You've been invited 🎉

@if ($inviterName)
**{{ $inviterName }}** has invited you to join **{{ $accountName }}**'s team on {{ config('app.name') }} as **{{ $roleName }}**.
@else
You've been invited to join **{{ $accountName }}**'s team on {{ config('app.name') }} as **{{ $roleName }}**.
@endif

Click the button below to verify your email and set up your access. What you can see and do is defined by the **{{ $roleName }}** role.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

This invitation expires {{ $expiresAt->diffForHumans() }}. If you weren't expecting it, you can safely ignore this email — no account will be created.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
