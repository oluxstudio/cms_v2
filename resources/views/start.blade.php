<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>Set up your site — {{ config('app.name', 'Olux') }}</title>
    </head>
    <body class="min-h-screen antialiased" style="background:#120f14">
        <x-auth-shell>
            <livewire:signup-wizard :embedded="true" />
        </x-auth-shell>
        <x-confirm-modal />
        @fluxScripts
    </body>
</html>
