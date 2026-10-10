<x-mail::message>
# Your payment didn't go through

Hi {{ $member->name }}, we couldn't take the latest payment for your **{{ $member->tier?->name ?? '' }}** membership of {{ $siteTitle }} ({{ $member->priceLabel() }}).

We'll try again automatically over the next few days. To keep your membership, please check your card details:

<x-mail::button :url="$manageUrl">
Manage my membership
</x-mail::button>

{{ $siteTitle }}
</x-mail::message>
