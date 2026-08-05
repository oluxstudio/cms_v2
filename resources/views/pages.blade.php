<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Pages</x-slot>
    <livewire:page-component :site="$site"/>
</x-layouts.selected>
