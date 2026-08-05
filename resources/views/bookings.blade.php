<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Bookings — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:bookings-page :site="$site" />
</x-layouts.selected>
