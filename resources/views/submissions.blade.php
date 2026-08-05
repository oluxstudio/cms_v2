<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Submissions — {{ $site->name }}</x-slot>
    <livewire:site-submissions-page :site="$site" />
</x-layouts.selected>
