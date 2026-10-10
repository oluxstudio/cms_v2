<x-mail::message>
# Welcome, {{ $member->name }}!

You're now a **{{ $member->tier?->name ?? 'member' }}** member of {{ $siteTitle }}.

@if (trim($welcome) !== '')
{{ $welcome }}
@endif

@if ($member->isPaid())
Your membership is {{ $member->priceLabel() }}{{ $member->renews_at ? ' and renews on '.$member->renews_at->format('j F Y') : '' }}.
@endif

Use this button to sign in to the members' area:

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

Next time, just ask for a new sign-in link on the site — no password needed.

{{ $siteTitle }}
</x-mail::message>
