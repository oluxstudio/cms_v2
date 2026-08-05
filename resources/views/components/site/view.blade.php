{{-- @props --}}
<div id="ViewSite" class="mx-auto" x-show="panels.view">
   <div class="flex justify-end">
       <span @click="openView('list')" class="btn-pri btn-small ">close</span>
   </div>
   @if ( $site) 
      <div class="max-w-4xl mx-auto">
         <h3 class="header-3 pl-7 my-3">View Site</h3>
         <p>View Site</p>
         <hr />
         <div class="flex flex-col gap-4 p-6" >
           
            <template x-for="(item, index) in fieldsObj"> 
               <div>
                  <template x-if="item.editable">
                     <div @dblclick="onUpdate(index)" class="header-child control">
                        <div class="label" x-text="item.name"></div>
                        <p class="content" x-show="!item.edit" x-text="item.value"> </p>
                        <div class="flex inline-control" x-show="item.edit">
                           <template x-if="item.type == 'text'">
                              <input  type="text" wire:model="form.name" x-model="item.value" placeholder="Name" />
                           </template>
                           <template x-if="item.type == 'textbox'">
                              <textarea wire:model="form.description" x-model="item.value" rows="6" placeholder="Description"></textarea>
                           </template>
                           <button class="btn-amber" class="amber" @click="updateField(item)">Update</button>
                        </div>
                     </div>
                  </template>
                  
                  <template x-if="!item.editable">
                     <div class="header-child">
                        <div class="label" x-text="item.name"></div>
                        <p class="content" x-show="!item.edit" x-text="item.value"> </p>
                     </div>
                  </template>
               </div>
            </template>
            
            <div class="flex gap-4">
               <button class="btn btn-green" @click="loadSite( '{{$site->name}}' )">Select Site</button>
               <button class="btn btn-red" @click="deleteItem( {{ $site->id }} )"> Delete</button>
            </div>
         </div>


      </div>
   @endif
   
   {{-- <form wire:submit.prevent="update">
       <label for="name">Name</label>
       <input type="text" wire:model="name" placeholder="Name" />
       <label for="domain">Domain</label>
       <input type="text" wire:model="domain" placeholder="Domain" />
       <button type="submit" class="btn px-8 py-2 bg-indigo-600 text-accent-foreground rounded-lg">Save</button>
   </form> --}}
</div>