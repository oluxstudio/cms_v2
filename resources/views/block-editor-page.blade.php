<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>{{ ucwords(str_replace('-', ' ', $site->name)) }} — Blocks</x-slot>

    <livewire:block-editor :site-id="$site->id" :key="'block-editor-'.$site->id" />
</x-layouts.selected>
