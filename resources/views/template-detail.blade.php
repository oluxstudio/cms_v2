<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ request()->cookie('theme') === 'light' ? 'light' : '' }}">
    <head>
        @include('partials.head')
        @include('partials.public-theme')
        <title>{{ ucfirst(str_replace('-', ' ', $key)) }} — Templates</title>
    </head>
    <body class="pub-theme antialiased">
        <livewire:template-detail-page :template-key="$key" />
        @fluxScripts
    </body>
</html>
