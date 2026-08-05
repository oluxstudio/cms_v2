<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class PageForm extends Form
{    
    #[Validate('required|min:4|alpha_dash')]
    public $name = 'home';
 
    #[Validate('required')]
    public $url = '/';

    #[Validate('required')]
    public $keywords = 'home';
}
