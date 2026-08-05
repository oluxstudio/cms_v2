@props(['sites'])


<div>
   <h1 class="text-2xl font-bold my-6">All Sites</h1>
   <div class="flex justify-stretch px-14">
      <h4></h4>
      <div class="ms-auto flex search-control">
         <input type="text" wire:model="search" placeholder="Search" />
         <button wire:click="clearSearch" class="btn-search btn-blue">search</button>
      </div>        
   </div>

   <div class="flex justify-between px-4 my-10">
      <h4>Total Sites: {{ count($sites) }}</h4>
      <button @click="openView('create')" class="btn-main btn-pri">Add Site</button>
   </div>

   
   <ul class="w-full mx-auto grid grid-rows-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
       @foreach ($sites as $site)
           <li x-data class="bg-slate-100 p-4 rounded-xl border-2">
               <div class="flex gap-5 justify-between">
                  <div class="flex flex-col gap-4">
                     <div class="header-child">
                        <div class="label">Name</div>
                        <div class="content">{{ $site['name'] }} </div>
                     </div>
                     
                     <div class="header-child">
                        <div class="label">domain</div>
                        <div class="content">{{ $site['domain'] }} </div>
                     </div>
                  </div>
                  <div class="flex flex-col gap-4 justify-between">
                     <div class="btn-main btn-pri" @click="viewSite( {{ $site }} )">view site</div>
                     <button wire:click="delete({{ $site['name'] }})" data-confirm="Delete this site? All its pages and data are removed." class="btn px-4 py-2 bg-rose-600 text-accent-foreground rounded-lg">Delete</button>
                  </div>
               </div>
           </li>
       @endforeach
   </ul>
</div>