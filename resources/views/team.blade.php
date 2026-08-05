<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Team — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-team-page :site="$site" />
</x-layouts.selected>
