<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitReached;
use App\Models\Site;
use App\Services\MediaStore;
use App\Support\EmailTemplate;
use App\Support\EmailTemplateCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Emails page — every outbound email the site sends, admin-editable.
 * No template selected → the grouped catalog list (Customized/Default badges).
 * ?tpl={key} → the editor for that template: subject + ordered, reorderable,
 * toggleable SECTIONS with a per-template placeholder legend and live preview.
 * Stored as site attributes; consumed by the mailables via EmailTemplate.
 */
class SiteEmailsPage extends Component
{
    use WithFileUploads;

    public Site $site;

    #[Url(as: 'tpl')]
    public ?string $tpl = null;

    public string $subject = '';

    /** @var list<array{key:string,enabled:bool,text:?string}> ordered sections */
    public array $sections = [];

    public string $logo = '';        // stored URL (shared across all templates)

    public $logoUpload;              // transient upload

    public string $successMessage = '';

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'forms.manage'), 403);
        $this->site = $site;
        $this->logo = (string) $site->getAttr('email.logo', '');
        if ($this->tpl !== null && ! EmailTemplateCatalog::exists($this->tpl)) {
            $this->tpl = null;
        }
        if ($this->tpl !== null) {
            $this->loadTemplate($this->tpl);
        }
    }

    // ── List ↔ editor navigation ───────────────────────────────────

    public function edit(string $key): void
    {
        abort_unless(EmailTemplateCatalog::exists($key), 404);
        $this->tpl = $key;
        $this->loadTemplate($key);
        $this->successMessage = '';
    }

    public function backToList(): void
    {
        $this->reset(['tpl', 'subject', 'sections', 'successMessage']);
    }

    private function loadTemplate(string $key): void
    {
        $tpl = EmailTemplate::forKey($this->site, $key);
        $this->subject = $tpl['subject'];
        $this->sections = $tpl['sections'];
    }

    /** Uploaded logos land in the site's Asset library (so they're re-pickable). */
    public function updatedLogoUpload(): void
    {
        // NB: the plain `image` rule rejects SVG in Laravel 11 — allow it explicitly.
        $this->validate(['logoUpload' => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif,svg', 'max:4096']]);
        try {
            $media = app(MediaStore::class)->store($this->site, $this->logoUpload);
        } catch (PlanLimitReached $e) {
            $this->logoUpload = null;
            $this->dispatch('upgrade-required', reason: $e->getMessage(), cta: $e->cta);

            return;
        }
        $this->logo = $media->publicUrl();
        $this->logoUpload = null;
        $this->site->setAttr('email.logo', $this->logo);
        $this->successMessage = 'Logo uploaded to your assets and applied to every email.';
    }

    public function removeLogo(): void
    {
        $this->logo = '';
        $this->site->forgetAttr('email.logo');
        $this->successMessage = 'Logo removed — emails fall back to the site name.';
    }

    public function saveLogo(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'forms.manage'), 403);
        $this->validate(['logo' => ['nullable', 'string', 'max:2048']]);
        $this->logo === '' ? $this->site->forgetAttr('email.logo') : $this->site->setAttr('email.logo', $this->logo);
        $this->successMessage = 'Logo saved — it appears on every email.';
    }

    // ── Section ordering / visibility ──────────────────────────────

    public function moveSectionUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->sections[$index])) {
            return;
        }
        [$this->sections[$index - 1], $this->sections[$index]] = [$this->sections[$index], $this->sections[$index - 1]];
    }

    public function moveSectionDown(int $index): void
    {
        if ($index >= count($this->sections) - 1) {
            return;
        }
        [$this->sections[$index], $this->sections[$index + 1]] = [$this->sections[$index + 1], $this->sections[$index]];
    }

    /** Put the editor back on the catalog defaults (not yet saved). */
    public function resetTemplate(): void
    {
        if (! $this->tpl) {
            return;
        }
        $this->subject = (string) EmailTemplateCatalog::get($this->tpl)['subject'];
        $this->sections = EmailTemplate::defaultSections($this->tpl);
        $this->successMessage = 'Template reset to the default layout (not yet saved).';
    }

    /** Drop the stored customisation entirely — back to catalog defaults. */
    public function resetToDefault(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'forms.manage'), 403);
        if (! $this->tpl) {
            return;
        }
        EmailTemplate::resetFor($this->site, $this->tpl);
        $this->loadTemplate($this->tpl);
        $this->successMessage = 'Customisation removed — this email uses the default template again.';
    }

    public function save(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'forms.manage'), 403);
        if (! $this->tpl) {
            return;
        }
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'sections' => ['array'],
            'sections.*.key' => ['required', 'string'],
            'sections.*.text' => ['nullable', 'string', 'max:5000'],
        ]);

        EmailTemplate::saveFor($this->site, $this->tpl, $this->subject, $this->sections);
        $this->successMessage = EmailTemplateCatalog::get($this->tpl)['label'].' email saved.';
    }

    /** Live preview: fill placeholders with the template's sample data. */
    public function getPreviewProperty(): array
    {
        $key = $this->tpl ?? 'receipt';
        $entry = EmailTemplateCatalog::get($key);
        $siteName = ucwords(str_replace('-', ' ', $this->site->name));

        $ctx = $entry['sample']['ctx'] ?? [];
        // null ctx values mean "this site's name/business" — resolve live.
        foreach ($ctx as $k => $v) {
            if ($v === null) {
                $ctx[$k] = $siteName;
            }
        }
        $ctx['site'] = $ctx['site'] ?? $siteName;
        $summary = $entry['sample']['summary'] ?? [];

        $sections = EmailTemplate::renderSections(['sections' => $this->sections], $ctx, $summary);
        $sections = collect($sections)
            ->map(fn ($s) => $s + ['label' => EmailTemplate::label($s['key'], $key)])
            ->values()
            ->all();

        return [
            'subject' => EmailTemplate::fill($this->subject !== '' ? $this->subject : $entry['subject'], $ctx, $summary),
            'sections' => $sections,
            'sample' => $summary,
            'dynamic' => $entry['sample']['dynamic'] ?? [],
        ];
    }

    public function render()
    {
        $attrMap = $this->site->attrMap();
        $customized = collect(EmailTemplateCatalog::keys())
            ->mapWithKeys(fn ($k) => [$k => EmailTemplateCatalog::isCustomized($this->site, $k, $attrMap)])
            ->all();

        $entry = $this->tpl ? EmailTemplateCatalog::get($this->tpl) : null;

        return view('livewire.site-emails-page', [
            'grouped' => EmailTemplateCatalog::grouped($this->site),
            'customized' => $customized,
            'entry' => $entry,
            'editableKeys' => $this->tpl ? EmailTemplate::editable($this->tpl) : [],
            'labels' => collect($this->sections)->mapWithKeys(fn ($s) => [$s['key'] => EmailTemplate::label($s['key'], $this->tpl ?? 'receipt')])->all(),
            'placeholders' => $entry['placeholders'] ?? [],
        ]);
    }
}
