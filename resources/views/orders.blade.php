<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Orders — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:orders-page :site="$site" />
</x-layouts.selected>
