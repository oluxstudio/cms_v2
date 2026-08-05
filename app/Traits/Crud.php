<?php

namespace App\Traits;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

trait Crud
{
   public $crud_actions = [
      'created'=>false,
      'deleted'=>false,
      'updated'=>false,
   ];

   public function crud_create( $table, $input_data )
   {
      try {
         DB::table($table)->insert($input_data);
         $this->crud_actions['created'] = true;
         return true;
      } catch (\Throwable $th) {
         $this->crud_actions['created'] = false;
         return false;
      }
   }
   
}