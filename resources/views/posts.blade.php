<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Posts — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:posts-page :site="$site" />
</x-layouts.selected>
