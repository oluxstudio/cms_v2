<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class PageForm extends Form
{
    #[Validate('required|string|min:2|max:120')]
    public $name = 'home';

    #[Validate(['required', 'string', 'max:190', 'regex:/^\/[a-zA-Z0-9\-_\/]*$/'], message: ['url.regex' => 'URLs start with / and may only contain letters, numbers, dashes and slashes.'])]
    public $url = '/';

    #[Validate('nullable|string|max:500')]
    public $keywords = '';
}
