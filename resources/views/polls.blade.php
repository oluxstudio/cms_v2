<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Polls — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:polls-page :site="$site" />
</x-layouts.selected>
