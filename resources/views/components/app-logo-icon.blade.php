{{-- Olux brand mark — hexagonal cube. Monochrome via currentColor so it
     tints with the surrounding text-* / fill-current utilities (white on the
     orange login chip, black/white in the auth cards, sidebar, etc.). --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 44" fill="none" {{ $attributes }}>
    {{-- outer hexagon ring --}}
    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"
        d="M20 0 38 10.4v20.8L20 41.6 2 31.2V10.4L20 0Zm0 4.62L6 12.71v16.18L20 36.98l14-8.09V12.71L20 4.62Z" />
    {{-- inner isometric cube --}}
    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"
        d="M20 11.2 30.4 17.2v11.99L20 35.2 9.6 29.2V17.2L20 11.2Zm0 2.77-8 4.62v9.22l8 4.62 8-4.62v-9.22l-8-4.62Z" />
    {{-- cube centre spark --}}
    <path fill="currentColor" d="M20 19.4 23.2 21.2 20 23.1 16.8 21.2 20 19.4Z" />
</svg>
