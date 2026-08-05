<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class SiteForm extends Form
{
    
    #[Validate('required|min:4|alpha_dash')]
    public $name = '';

    #[Validate('required')]
    public $domain = '';

    #[Validate('required')]
    public $description = '';

    #[Validate('required')]
    public $owner = '';
}
