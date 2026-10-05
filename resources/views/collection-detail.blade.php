<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | {{ $collection->name }}</x-slot>
    <livewire:collection-detail-page :site="$site" :collection="$collection->id"/>
</x-layouts.selected>
