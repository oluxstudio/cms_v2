<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Livewire\Forms\PageForm;
use Livewire\Component;
use App\Models\Site;
use App\Models\Page;

class PageComponent extends Component
{
    use WithLayoutMode;

    public Site $site;
    public string $search = '';
    public bool $showModal = false;
    public int $editingId = 0;
    public PageForm $form;

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('pages', 'list');
    }

    public function render()
    {
        $pages = Page::where('site_id', $this->site->id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('url', 'like', '%'.$this->search.'%')
                  ->orWhere('keywords', 'like', '%'.$this->search.'%');
            }))
            ->latest()
            ->get();

        $total       = Page::where('site_id', $this->site->id)->count();
        $thisWeek    = Page::where('site_id', $this->site->id)
                           ->where('created_at', '>=', now()->startOfWeek())->count();
        $avgKeywords = Page::where('site_id', $this->site->id)->get()
                           ->avg(fn ($p) => count(array_filter(explode(',', $p->keywords))));

        return view('livewire.page-component', [
            'pages'       => $pages,
            'total'       => $total,
            'thisWeek'    => $thisWeek,
            'avgKeywords' => round($avgKeywords ?? 0),
        ]);
    }

    public function openCreate(): void
    {
        $this->form->reset();
        $this->editingId = 0;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $page = Page::findOrFail($id);
        $this->editingId      = $id;
        $this->form->name     = $page->name;
        $this->form->url      = $page->url;
        $this->form->keywords = $page->keywords;
        $this->showModal      = true;
    }

    public function save(): void
    {
        $this->form->validate();

        if ($this->editingId) {
            Page::findOrFail($this->editingId)->update([
                'name'     => $this->form->name,
                'url'      => $this->form->url,
                'keywords' => $this->form->keywords,
            ]);
        } else {
            Page::create([
                'site_id'  => $this->site->id,
                'name'     => $this->form->name,
                'url'      => $this->form->url,
                'keywords' => $this->form->keywords,
            ]);
        }

        $this->showModal = false;
        $this->form->reset();
    }

    /** Delete a page — confirmation happens in the shared modal (data-confirm). */
    public function deletePage(int $id): void
    {
        Page::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
