<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Events</x-slot>
    <livewire:events-page :site="$site" />
</x-layouts.selected>
