<x-layouts.selected :siteName="$site->name">
    <x-slot:title>API Keys — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-api-keys :site="$site" />
</x-layouts.selected>
