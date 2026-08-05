<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Templates — {{ $site->name }}</x-slot>
    <livewire:site-templates-page :site="$site" />
</x-layouts.selected>
