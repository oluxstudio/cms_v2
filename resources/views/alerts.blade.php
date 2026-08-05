<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ ucwords(str_replace("-", " ", $site->name)) }} — Alerts</x-slot>
    <livewire:alerts-page :site-id="$site->id" />
</x-layouts.selected>
