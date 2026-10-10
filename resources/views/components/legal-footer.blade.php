{{-- Small site-wide footer for Olux's own public pages: copyright + Privacy policy link. Inherits the page's text colour. --}}
<footer {{ $attributes->merge(['class' => 'w-full text-center text-xs py-5 px-4']) }} style="opacity:.7">
    © {{ date('Y') }} Olux Studio
    <span aria-hidden="true">·</span>
    <a href="{{ route('privacy') }}" class="underline-offset-2 hover:underline" style="color:inherit">Privacy policy</a>
    <span aria-hidden="true">·</span>
    <a href="{{ route('privacy') }}#data-deletion" class="underline-offset-2 hover:underline" style="color:inherit">Delete your data</a>
</footer>
