<x-layouts.selected :site-name="$site->name" :site-id="$site->id">
   <x-slot:title>
       Olux | Sites
   </x-slot>
   <div>Selected page</div>
   <livewire:page-component :site="$site"/>
</x-layouts.selected>