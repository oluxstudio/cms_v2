<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Earnings</x-slot>
    <livewire:earnings-page :site="$site"/>
</x-layouts.selected>
