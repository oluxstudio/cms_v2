<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Store — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:products-page :site="$site" />
</x-layouts.selected>
