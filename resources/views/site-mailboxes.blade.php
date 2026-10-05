<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Business email — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-mailboxes-page :site="$site" />
</x-layouts.selected>
