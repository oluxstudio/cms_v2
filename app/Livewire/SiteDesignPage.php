<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Jobs\ProcessTemplateUpload;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplateUpload;
use App\Services\DesignService;
use App\Services\TemplateCommerce;
use App\Support\CuratedTemplates;
use App\Support\TemplatePaths;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Site › Design: the site's current template + a "Change template" picker
 * reading from the account's LIBRARY. Applying saves a restore point first;
 * the toast offers Undo (revert).
 */
class SiteDesignPage extends Component
{
    use WithFileUploads;

    public Site $site;

    /** Upload-your-own: a zipped Nuxt app + an optional display name. */
    public $appZip = null;

    public string $uploadName = '';

    public bool $picking = false;

    public ?string $selectedId = null;

    public bool $canUndo = false;

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'addons.manage'), 403);
    }

    public function openPicker(): void
    {
        $this->picking = true;
        $this->selectedId = null;
    }

    public function closePicker(): void
    {
        $this->picking = false;
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    public function apply(DesignService $design): void
    {
        abort_if($this->selectedId === null, 422);
        // Private = the account's own uploads; DesignService still gates on the library.
        $template = Template::whereIn('status', ['published', 'private'])->findOrFail($this->selectedId);
        $result = $design->apply(Auth::user(), $this->site, $template);
        $this->site->refresh();
        $this->picking = false;
        $this->canUndo = true;

        $msg = $result['applied'].' is now this site\'s design.';
        if ($result['features_enabled'] !== []) {
            $msg .= ' Turned on: '.implode(', ', $result['features_enabled']).'.';
        }
        $this->dispatch('toast', level: 'success', title: 'Template applied', message: $msg);
    }

    /** Take a zipped Nuxt app; scanning + building continue in the background. */
    public function uploadApp(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'addons.manage'), 403);
        $this->validate([
            'appZip' => ['required', 'file', 'mimes:zip', 'max:'.(int) config('templates.uploads.max_zip_kb', 61440)],
            'uploadName' => ['nullable', 'string', 'max:60'],
        ], [
            'appZip.required' => 'Choose a .zip of your Nuxt app first.',
            'appZip.mimes' => 'Upload a .zip file.',
            'appZip.max' => 'The zip is too large (60 MB max). Leave out node_modules, .nuxt and .output.',
        ]);

        $running = TemplateUpload::where('user_id', Auth::id())
            ->whereIn('status', [TemplateUpload::QUEUED, TemplateUpload::SCANNING, TemplateUpload::BUILDING])->count();
        if ($running >= 2) {
            $this->addError('appZip', 'Two uploads are already being processed. Wait for one to finish.');

            return;
        }

        $upload = TemplateUpload::create([
            'user_id' => Auth::id(),
            'site_id' => $this->site->id,
            'key' => TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10)),
            'name' => trim($this->uploadName) ?: null,
            'original_filename' => Str::limit($this->appZip->getClientOriginalName(), 180, ''),
            'status' => TemplateUpload::QUEUED,
            'step' => 'Waiting to start',
        ]);
        File::ensureDirectoryExists(dirname($upload->zipPath()));
        File::copy($this->appZip->getRealPath(), $upload->zipPath());
        ProcessTemplateUpload::dispatch($upload->id);

        $this->reset('appZip', 'uploadName');
        $this->dispatch('toast', level: 'success', title: 'Upload received',
            message: 'We\'re checking and building your design. This usually takes a few minutes; you can leave this page.');
    }

    /** Apply a finished upload straight to this site. */
    public function applyUpload(string $uploadId, DesignService $design): void
    {
        $upload = TemplateUpload::where('user_id', Auth::id())->where('status', TemplateUpload::READY)->findOrFail($uploadId);
        $this->selectedId = $upload->template_id;
        $this->apply($design);
    }

    public function dismissUpload(string $uploadId): void
    {
        TemplateUpload::where('user_id', Auth::id())->where('status', TemplateUpload::FAILED)->whereKey($uploadId)->delete();
    }

    public function undo(DesignService $design): void
    {
        $design->revert(Auth::user(), $this->site);
        $this->site->refresh();
        $this->canUndo = false;
        $this->dispatch('toast', level: 'success', title: 'Restored', message: 'The site is back on its previous design.');
    }

    public function render(TemplateCommerce $commerce)
    {
        $user = Auth::user();
        $applied = $this->site->installedTemplates()->whereNotNull('applied_at')->with('template')->first();
        // Legacy sites bound by renderer key without a catalog row still show something.
        $current = $applied?->template
            ?? ($applied ? null : null);
        $curatedFallback = ! $applied && $this->site->template && $this->site->template !== 'blank'
            ? CuratedTemplates::find($this->site->template)
            : null;

        $libraryTemplates = TemplateEntitlement::with('template.creator')
            ->where('user_id', $user->id)->latest()->get()
            ->pluck('template')->filter()->values();

        $selected = $this->selectedId ? $libraryTemplates->firstWhere('id', $this->selectedId) : null;
        $wouldEnable = $selected
            ? collect((array) $selected->required_features)
                ->filter(fn ($k) => FeatureRegistry::exists($k) && ! $this->site->hasFeature($k))
                ->map(fn ($k) => FeatureRegistry::get($k)['name'] ?? ucfirst($k))->values()
            : collect();

        $uploads = TemplateUpload::with('template')->where('user_id', $user->id)
            ->where('site_id', $this->site->id)->latest()->limit(6)->get();

        return view('livewire.site-design-page', [
            'uploads' => $uploads,
            'applied' => $applied,
            'current' => $current,
            'curatedFallback' => $curatedFallback,
            'libraryTemplates' => $libraryTemplates,
            'selected' => $selected,
            'wouldEnable' => $wouldEnable,
        ]);
    }
}
