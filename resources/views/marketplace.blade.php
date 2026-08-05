<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>Marketplace — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:marketplace-page :site="$site" />
</x-layouts.selected>
