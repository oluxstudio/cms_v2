<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Properties — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-properties-page :site="$site" />
</x-layouts.selected>
