<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ request()->cookie('theme') === 'light' ? 'light' : '' }}">
    <head>
        @include('partials.head')
        @include('partials.public-theme')
        <title>Templates — {{ config('app.name', 'Olux') }}</title>
    </head>
    <body class="pub-theme antialiased">
        <livewire:template-gallery-page />
        @fluxScripts
    </body>
</html>
