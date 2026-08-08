<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Livewire\Forms\PageForm;
use App\Models\Page;
use App\Models\Site;
use Livewire\Component;

class PageComponent extends Component
{
    use WithLayoutMode;

    public Site $site;

    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public PageForm $form;

    // ── Component picker (attach content components to a page) ──
    public ?string $pickerPageId = null;

    public string $pickerSearch = '';

    public string $pickerTag = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('pages', 'list');
    }

    public function openPicker(string $pageId): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $this->pickerPageId = Page::where('site_id', $this->site->id)->findOrFail($pageId)->id;
        $this->reset(['pickerSearch', 'pickerTag']);
    }

    public function closePicker(): void
    {
        $this->reset(['pickerPageId', 'pickerSearch', 'pickerTag']);
    }

    public function getPickerPageProperty(): ?Page
    {
        return $this->pickerPageId
            ? Page::where('site_id', $this->site->id)->find($this->pickerPageId)
            : null;
    }

    /** Site components filtered by the picker's search + tag. */
    public function getPickerComponentsProperty()
    {
        return $this->site->contentComponents()->with('nodes')
            ->when($this->pickerSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->pickerSearch.'%'))
            ->get()
            ->when($this->pickerTag !== '', fn ($c) => $c->filter(
                fn ($comp) => in_array($this->pickerTag, $comp->tags ?? [], true)
            )->values());
    }

    /** Every tag used across the site's components — the filter chips. */
    public function getComponentTagsProperty(): array
    {
        return $this->site->contentComponents()->pluck('tags')
            ->flatMap(fn ($t) => $t ?? [])->unique()->sort()->values()->all();
    }

    /** Attach/detach a component on the picker's page (order appends). */
    public function toggleComponent(string $componentId): void
    {
        abort_unless($this->site->allows(auth()->user(), 'pages.manage'), 403);
        $page = $this->pickerPage;
        $component = $this->site->contentComponents()->findOrFail($componentId);
        if (! $page) {
            return;
        }

        if ($page->components()->where('components.id', $component->id)->exists()) {
            $page->components()->detach($component->id);
        } else {
            $order = (int) \DB::table('page_component')->where('page_id', $page->id)->max('order') + 1;
            $page->components()->attach($component->id, ['order' => $order]);
        }
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

        $total = Page::where('site_id', $this->site->id)->count();
        $thisWeek = Page::where('site_id', $this->site->id)
            ->where('created_at', '>=', now()->startOfWeek())->count();
        $avgKeywords = Page::where('site_id', $this->site->id)->get()
            ->avg(fn ($p) => count(array_filter(explode(',', $p->keywords))));

        return view('livewire.page-component', [
            'pages' => $pages,
            'total' => $total,
            'thisWeek' => $thisWeek,
            'avgKeywords' => round($avgKeywords ?? 0),
        ]);
    }

    public function openCreate(): void
    {
        $this->form->reset();
        $this->editingId = 0;
        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        $page = Page::findOrFail($id);
        $this->editingId = $id;
        $this->form->name = $page->name;
        $this->form->url = $page->url;
        $this->form->keywords = $page->keywords;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->form->validate();

        if ($this->editingId) {
            Page::findOrFail($this->editingId)->update([
                'name' => $this->form->name,
                'url' => $this->form->url,
                'keywords' => $this->form->keywords,
            ]);
        } else {
            Page::create([
                'site_id' => $this->site->id,
                'name' => $this->form->name,
                'url' => $this->form->url,
                'keywords' => $this->form->keywords,
            ]);
        }

        $this->showModal = false;
        $this->form->reset();
    }

    /** Delete a page — confirmation happens in the shared modal (data-confirm). */
    public function deletePage(string $id): void
    {
        Page::where('site_id', $this->site->id)->findOrFail($id)->delete();
    }
}
