<x-layouts.selected :siteName="$site->name" :site-id="$site->id">
    <x-slot:title>{{ $product->name }} — {{ ucwords(str_replace('-', ' ', $site->name)) }}</x-slot>
    <livewire:product-detail-page :site="$site" :product="$product" />
</x-layouts.selected>
