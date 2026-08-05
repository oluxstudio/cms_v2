<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Collections</x-slot>
    <livewire:collections-page :site="$site"/>
</x-layouts.selected>
