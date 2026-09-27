<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>Template — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:marketplace-template-page :site="$site" :slug="$slug" />
</x-layouts.selected>
