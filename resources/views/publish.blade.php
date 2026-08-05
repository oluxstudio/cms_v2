<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Go live — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:go-live-page :site="$site" />
</x-layouts.selected>
