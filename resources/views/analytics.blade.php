<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>Analytics | {{ $site->name }}</x-slot>
    <livewire:analytics-dashboard :site="$site" />
</x-layouts.selected>
