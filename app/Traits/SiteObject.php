<?php

namespace App\Traits;

use App\Models\Site;

trait SiteObject
{
   /**
    * Find a site by a given id.
    *
    * @param string $id
    * @return Site
    */
   public function getSitebyID($id)
   {
      $site = Site::find($id);
      

      return $site;
   }
   
   /**
    * Find a site by a given slug.
    *
    * @param string $slug
    * @return Site
    */
   public function getSitebySlug($slug)
   {
      return \Illuminate\Support\Str::slug($string);
   }
}