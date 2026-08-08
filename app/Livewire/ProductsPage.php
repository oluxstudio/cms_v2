<?php

namespace App\Livewire;

use App\Models\Site;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductsPage extends Component
{
    use WithFileUploads;

    public Site $site;

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $search = '';

    // Form
    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $inventory = '';

    public bool $is_active = true;

    public $photo;

    public ?string $existingImage = null;

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function getProductsProperty()
    {
        return $this->site->products()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('sort')
            ->latest()
            ->get();
    }

    public function getCurrencyProperty(): string
    {
        return $this->site->currency ?? 'gbp';
    }

    public function getProductLimitProperty(): int
    {
        return (int) ($this->site->feature('store')['product_limit'] ?? 50);
    }

    public function create(): void
    {
        if ($this->site->products()->count() >= $this->productLimit) {
            $this->errorMessage = "You've reached the product limit ({$this->productLimit}). Raise it in Marketplace → Store settings.";

            return;
        }
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $p = $this->site->products()->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->description = $p->description ?? '';
        $this->price = (string) $p->priceMajor();
        $this->inventory = $p->inventory === null ? '' : (string) $p->inventory;
        $this->is_active = $p->is_active;
        $this->existingImage = $p->image;
        $this->photo = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'inventory' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'currency' => $this->currency,
            'inventory' => $validated['inventory'] !== '' && $validated['inventory'] !== null ? (int) $validated['inventory'] : null,
            'is_active' => $this->is_active,
        ];

        if ($this->photo) {
            $data['image'] = $this->photo->store('products', 'public');
        }

        if ($this->editingId) {
            $this->site->products()->findOrFail($this->editingId)->update($data);
            $this->successMessage = 'Product updated.';
        } else {
            if ($this->site->products()->count() >= $this->productLimit) {
                $this->errorMessage = 'Product limit reached.';

                return;
            }
            // Ensure unique slug within the site
            $base = Str::slug($data['name']);
            $slug = $base;
            $i = 1;
            while ($this->site->products()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $this->site->products()->create($data + ['slug' => $slug]);
            $this->successMessage = 'Product created.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $p = $this->site->products()->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    public function delete(string $id): void
    {
        $this->site->products()->findOrFail($id)->delete();
        $this->successMessage = 'Product deleted.';
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'price', 'inventory', 'photo', 'existingImage']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.products-page');
    }
}
