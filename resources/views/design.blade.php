<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Design — {{ $site->name }}</x-slot>
    <livewire:site-design-page :site="$site" />
</x-layouts.selected>
