<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>Payments — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-payments-page :site="$site" />
</x-layouts.selected>
