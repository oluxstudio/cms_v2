<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Emails — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:site-emails-page :site="$site" />
</x-layouts.selected>
