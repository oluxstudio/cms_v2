<x-mail::message>
# Sign in to {{ $siteTitle }}

Hi {{ $member->name }}, here's your sign-in link. It works for the next {{ $minutes }} minutes.

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

If you didn't ask for this, you can ignore this email — nobody can sign in without the link.

{{ $siteTitle }}
</x-mail::message>
