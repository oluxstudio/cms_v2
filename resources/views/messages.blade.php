<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ ucwords(str_replace("-", " ", $site->name)) }} — Messages</x-slot>
    <livewire:messages-page :site-id="$site->id" />
</x-layouts.selected>
