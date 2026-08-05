<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>Invoices — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:invoices-page :site="$site" />
</x-layouts.selected>
