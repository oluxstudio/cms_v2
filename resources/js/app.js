// Bundle resources/images so Vite::asset() can reference them.
import.meta.glob([
  '../images/**',
]);

import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// document.addEventListener('alpine:init', () => {
//    Alpine.data('viewObject', () => ({
//        body: '',
//        selectedSite: 'sdsdsd',
//        panels:{
//            list:true,
//            create:false,
//            view:false,
//        },
//        openCreateForm() {
           
//        },
//        submitComment() {
//            // Call the Livewire method
//            this.$wire.storeComment();
//            // Optionally clear the textarea after submission
//            this.body = '';
//        }
//    }));
// });