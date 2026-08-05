<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Donations — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:donations-page :site="$site" />
</x-layouts.selected>
