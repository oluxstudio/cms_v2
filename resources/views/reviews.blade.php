<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Reviews</x-slot>
    <livewire:reviews-page :site="$site"/>
</x-layouts.selected>
