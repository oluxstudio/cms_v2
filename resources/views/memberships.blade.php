<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Members</x-slot>
    <livewire:memberships-page :site="$site" />
</x-layouts.selected>
