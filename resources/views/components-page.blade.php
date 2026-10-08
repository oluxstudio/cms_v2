<x-layouts.selected :siteName="$site->name">
    <x-slot:title>Components — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:components-page :site="$site" :screen="$screen ?? null" :component="$componentId ?? null" />
</x-layouts.selected>
