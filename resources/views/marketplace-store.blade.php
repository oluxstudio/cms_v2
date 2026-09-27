<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>Templates — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:marketplace-store :site="$site" />
</x-layouts.selected>
