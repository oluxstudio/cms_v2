<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ request()->cookie('theme') === 'light' ? 'light' : '' }}">
    <head>
        @include('partials.head')
        @include('partials.public-theme')
        <title>Share your experience — {{ config('app.name', 'Olux') }}</title>
        <meta name="description" content="Tell us how Olux has helped your business. Your words may appear on our website.">
    </head>
    <body class="pub-theme antialiased">
        <livewire:testimonial-submit-page />
        <x-legal-footer />
        @fluxScripts
    </body>
</html>
