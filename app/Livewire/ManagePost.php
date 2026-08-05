<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class ManagePost extends Component
{
    use WithFileUploads;
 
    public $file;
    public $uploadedFile;
    
    #[Validate('image|max:1024')] // 1MB Max
    public $photo;
 
    #[Validate('required')] // 1MB Max
    public $title;
    public $content;
    public function updatedFile()
    {
        $this->validate([
            'file' => 'image|max:10240', // Validate on select
        ]);
    }
    
    public function removeFile()
    {
        $this->reset('file');
    }

    public function save()
    {

        $this->validate();

        $form = [
            'title' => $this->title,
            'photo' => $this->photo
        ];
        // $path = $this->photo->store(path: 'uploads');
        $path = $this->photo->store('uploads', 'public');

        dd($form, $path);
    }

    public function render()
    {
        return view('livewire.manage-post');
    }
}
