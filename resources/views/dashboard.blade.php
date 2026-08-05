<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
    <x-slot:title>
        {{ ucwords(str_replace('-', ' ', $site->name)) }} — Dashboard
    </x-slot>
    <livewire:site-dashboard :site="$site"/>
 </x-layouts.selected>