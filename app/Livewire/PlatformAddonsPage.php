<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Models\SiteFeature;
use App\Services\AccountActivity;
use App\Support\ConfigOverlay;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Platform admin › Add-ons: the feature catalogue from config/features.php
 * with admin overrides for the customer-facing copy, basic/premium tier,
 * availability and default settings. Navigation and the settings schema
 * stay in code.
 */
class PlatformAddonsPage extends Component
{
    public ?string $editing = null;

    public array $form = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function edit(string $key): void
    {
        $f = FeatureRegistry::get($key);
        abort_unless($f, 404);
        $this->editing = $key;
        $this->form = [
            'name' => (string) ($f['name'] ?? ''),
            'description' => (string) ($f['description'] ?? ''),
            'tier' => (string) ($f['tier'] ?? 'basic'),
            'available' => empty($f['hidden']),
            'defaults' => collect($f['settings'] ?? [])->map(fn ($field) => $field['default'] ?? null)->all(),
        ];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->editing = null;
    }

    public function save(): void
    {
        $f = FeatureRegistry::get((string) $this->editing);
        abort_unless($f, 404);
        $rules = [
            'form.name' => ['required', 'string', 'max:60'],
            'form.description' => ['required', 'string', 'max:600'],
            'form.tier' => ['required', 'in:basic,premium'],
        ];
        foreach ($f['settings'] ?? [] as $name => $field) {
            $rules["form.defaults.{$name}"] = match ($field['type'] ?? 'text') {
                'number' => ['nullable', 'numeric'],
                'toggle' => ['nullable', 'boolean'],
                'select' => ['nullable', 'in:'.implode(',', (array) ($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:2000'],
            };
        }
        $this->validate($rules, ['form.name.required' => 'Give the add-on a name.', 'form.description.required' => 'Describe what it does.']);

        $settings = [];
        foreach ($f['settings'] ?? [] as $name => $field) {
            $value = $this->form['defaults'][$name] ?? null;
            $settings[$name] = ['default' => match ($field['type'] ?? 'text') {
                'number' => $value === null || $value === '' ? null : $value + 0,
                'toggle' => (bool) $value,
                default => $value,
            }];
        }
        ConfigOverlay::set("features.{$this->editing}", [
            'name' => trim($this->form['name']),
            'description' => trim($this->form['description']),
            'tier' => $this->form['tier'],
            'hidden' => ! $this->form['available'],
            'settings' => $settings,
        ], 'merge');
        AccountActivity::record(Auth::id(), 'addon.edited', 'Edited the '.trim($this->form['name']).' add-on', ['category' => 'Sites']);

        $this->editing = null;
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'The add-on is updated for every site.');
    }

    public function toggleAvailable(string $key): void
    {
        $f = FeatureRegistry::get($key);
        abort_unless($f, 404);
        $stored = (array) (ConfigOverlay::stored("features.{$key}") ?? []);
        ConfigOverlay::set("features.{$key}", array_merge($stored, ['hidden' => empty($f['hidden'])]), 'merge');
    }

    public function resetAddon(string $key): void
    {
        ConfigOverlay::forget("features.{$key}");
        $this->editing = null;
        $this->dispatch('toast', level: 'success', title: 'Reset', message: 'Back to the built-in wording and settings.');
    }

    public function render()
    {
        $counts = SiteFeature::where('enabled', true)->selectRaw('`key`, count(*) as n')->groupBy('key')->pluck('n', 'key');
        $rows = collect(FeatureRegistry::all())->map(fn ($f, $key) => [
            'key' => $key,
            'f' => $f,
            'sites' => (int) ($counts[$key] ?? 0),
            'edited' => ConfigOverlay::stored("features.{$key}") !== null,
        ])->sortByDesc('sites')->values();

        return view('livewire.platform-addons-page', [
            'rows' => $rows,
            'stats' => [
                'addons' => $rows->count(),
                'available' => $rows->filter(fn ($r) => empty($r['f']['hidden']))->count(),
                'premium' => $rows->filter(fn ($r) => ($r['f']['tier'] ?? 'basic') === 'premium')->count(),
                'activations' => (int) $rows->sum('sites'),
                'top' => $rows->first(),
            ],
            'editingDef' => $this->editing ? FeatureRegistry::get($this->editing) : null,
        ]);
    }
}
