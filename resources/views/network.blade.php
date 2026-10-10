<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $site->name }} | Referral network</x-slot>
    <livewire:network-page :site="$site"/>
</x-layouts.selected>
