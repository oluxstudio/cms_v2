<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ ucwords(str_replace("-", " ", $site->name)) }} — Todos</x-slot>
    <livewire:todos-page :site-id="$site->id" />
</x-layouts.selected>
