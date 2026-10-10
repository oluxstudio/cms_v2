<x-mail::message>
# Your membership has ended

Hi {{ $member->name }}, your **{{ $member->tier?->name ?? '' }}** membership of {{ $siteTitle }} has been cancelled. Thank you for being a member.

You're welcome back any time:

<x-mail::button :url="$manageUrl">
Rejoin
</x-mail::button>

{{ $siteTitle }}
</x-mail::message>
