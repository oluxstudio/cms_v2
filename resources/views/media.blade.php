<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Assets</x-slot>
    <livewire:media-page :site="$site"/>
</x-layouts.selected>
