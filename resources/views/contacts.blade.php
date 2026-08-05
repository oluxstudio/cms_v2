<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Contacts — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-contacts-page :site="$site" />
</x-layouts.selected>
