<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Newsletter</x-slot>
    <livewire:newsletter-page :site="$site"/>
</x-layouts.selected>
