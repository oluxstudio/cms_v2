<x-layouts.home wide>
    <x-slot:title>{{ config('app.name') }} — Sites</x-slot>
    <div class="px-4 sm:px-6"><livewire:onboarding-checklist /></div>
    <livewire:site-component />
</x-layouts.home>
