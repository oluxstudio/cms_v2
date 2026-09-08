<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $page->name }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:page-detail-page :site="$site" :page="$page" />
</x-layouts.selected>
