{{--
    Contact avatar — custom logo/photo (data.avatar) or Gravatar, falling back
    to colored initials when no image exists. Alpine swaps to initials on
    img error, so missing Gravatars degrade instantly with no broken icon.
    Props: $contact, $size (wrapper classes), $text (initials text classes).
--}}
@props(['contact', 'size' => 'w-9 h-9', 'text' => 'text-xs'])
<div x-data="{ broken: false }" class="{{ $size }} rounded-full shrink-0 relative overflow-hidden">
    <img x-show="!broken" x-on:error="broken = true" src="{{ $contact->avatarUrl() }}" alt="{{ $contact->name }}"
         class="absolute inset-0 w-full h-full object-cover" loading="lazy">
    <div x-show="broken" x-cloak
         class="absolute inset-0 flex items-center justify-center text-white font-bold {{ $text }}"
         style="background:{{ $contact->avatarColor() }}">{{ $contact->initials() }}</div>
</div>
