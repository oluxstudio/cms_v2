<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Estimates — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:estimates-page :site="$site" />
</x-layouts.selected>
