<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Form Responses — {{ $site->name }}</x-slot>
    <livewire:site-forms-page :site="$site" />
</x-layouts.selected>
