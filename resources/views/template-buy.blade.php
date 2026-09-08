<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ request()->cookie('theme') === 'light' ? 'light' : '' }}">
    <head>
        @include('partials.head')
        @include('partials.public-theme')
        <title>Buy template — {{ config('app.name', 'Olux') }}</title>
    </head>
    <body class="pub-theme antialiased">
        <livewire:template-buy-page :template-key="$key" />
        @fluxScripts
    </body>
</html>
