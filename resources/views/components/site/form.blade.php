<div id="CreateSite" class=" mx-auto" x-show="panels.create">
   <div class="flex justify-end">
       <span @click="openView('list')" class="btn-pri btn-small ">close</span>
   </div>
   <div class="w-full lg:w-[50rem] mx-auto">
       <form @submit.prevent="createSite">
           <div class="input-control">
               <label for="name">Name</label>
               <input type="text" wire:model="form.name" placeholder="Name" />
           </div>

           <div class="input-control">
               <label for="domain">Domain</label>
               <input type="text" wire:model="form.domain" placeholder="Domain" />
           </div>

           <div class="input-control">
               <label for="domain">Owner</label>
               <input type="text" wire:model="form.owner" placeholder="Owner" />
           </div>

           <div class="input-control">
               <label for="domain">Description</label>
               <textarea wire:model="form.description" rows="6" placeholder="Description"></textarea>
           </div>
           
           <button type="submit" class="btn-main btn-go">Create</button>
       </form>
   </div>
</div>