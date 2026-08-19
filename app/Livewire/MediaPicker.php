<?php

namespace App\Livewire;

use App\Models\Media;
use App\Models\Site;
use App\Services\MediaStore;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Reusable media-picker modal. Mount once per page:
 *   <livewire:media-picker :site-id="$siteId" />
 *
 * Open from anywhere (Alpine/JS):  $dispatch('open-media-picker', { context: {...} })
 * On selection it dispatches:      media-picked { context, mediaRef, url }
 * — `mediaRef` is the portable "@media/{filename}" reference, `url` the stored
 * URL. (Named `mediaRef` because `ref` is a reserved dispatch param in Livewire.)
 */
class MediaPicker extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $siteId;

    public bool $open = false;

    public array $context = [];      // passed back verbatim with media-picked

    public string $search = '';

    public string $type = 'image';   // all | image | video | document

    public array $uploads = [];

    #[On('open-media-picker')]
    public function openPicker(array $context = []): void
    {
        $this->context = $context;
        $this->search = '';
        $this->resetPage();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, Media::TYPES, true) ? $type : 'all';
        $this->resetPage();
    }

    /** Pick an item → notify the host component and close. */
    public function pick(string $id): void
    {
        $media = Media::where('site_id', $this->siteId)->find($id);
        if (! $media) {
            return;
        }
        // NOTE: the param must not be named `ref` — Livewire consumes a `ref`
        // dispatch param as a component-ref TARGET and silently drops the event.
        $this->dispatch('media-picked', context: $this->context, mediaRef: $media->ref(), url: $media->url);
        $this->open = false;
    }

    /** Drag-drop / file-select upload directly inside the picker. */
    public function updatedUploads(): void
    {
        $this->validate(
            ['uploads' => ['array'], 'uploads.*' => ['file', 'max:51200']],
            ['uploads.*.max' => 'Each file must be 50 MB or smaller.'],
        );
        $site = Site::findOrFail($this->siteId);
        $store = app(MediaStore::class);
        foreach ($this->uploads as $file) {
            $store->store($site, $file);
        }
        $this->uploads = [];
        $this->resetPage();
    }

    public function render()
    {
        $items = Media::where('site_id', $this->siteId)
            ->when($this->type !== 'all', fn ($q) => $q->where('file_type', $this->type))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('alt_text', 'like', '%'.$this->search.'%')))
            ->orderByDesc('id')
            ->paginate(24, pageName: 'pickerPage');

        return view('livewire.media-picker', ['items' => $items]);
    }
}
