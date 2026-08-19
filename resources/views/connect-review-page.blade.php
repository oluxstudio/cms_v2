<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Site Connect — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:connect-review-page :site="$site" />
</x-layouts.selected>
