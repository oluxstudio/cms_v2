<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Components — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:components-page :site="$site" />
</x-layouts.selected>
