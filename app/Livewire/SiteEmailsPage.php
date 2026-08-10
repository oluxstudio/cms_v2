<?php

namespace App\Livewire;

use App\Mail\SubmissionReceipt;
use App\Models\Site;
use App\Services\MediaStore;
use App\Support\EmailTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Emails page — the site admin edits the branded receipt every visitor gets on
 * a submission (forms, contact, interest, bookings): the subject, the logo, and
 * an ordered set of reorderable/toggleable SECTIONS (greeting, message,
 * submission summary, footer). Stored as site attributes; consumed by the
 * SubmissionReceipt mailable, which shares the EmailTemplate engine used here.
 */
class SiteEmailsPage extends Component
{
    use WithFileUploads;

    public Site $site;

    public string $subject = '';

    /** @var list<array{key:string,enabled:bool,text:?string}> ordered sections */
    public array $sections = [];

    public string $logo = '';        // stored URL

    public $logoUpload;              // transient upload

    public string $successMessage = '';

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'forms.manage'), 403);
        $this->site = $site;
        $this->subject = (string) $site->getAttr('email.receipt_subject', SubmissionReceipt::defaultSubject());
        $this->sections = EmailTemplate::resolveSections($site->getAttr('email.receipt_sections'));
        $this->logo = (string) $site->getAttr('email.logo', '');
    }

    /** Uploaded logos land in the site's Asset library (so they're re-pickable). */
    public function updatedLogoUpload(): void
    {
        // NB: the plain `image` rule rejects SVG in Laravel 11 — allow it explicitly.
        $this->validate(['logoUpload' => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif,svg', 'max:4096']]);
        $media = app(MediaStore::class)->store($this->site, $this->logoUpload);
        $this->logo = $media->publicUrl();
        $this->logoUpload = null;
        $this->site->setAttr('email.logo', $this->logo);
        $this->successMessage = 'Logo uploaded to your assets and applied.';
    }

    public function removeLogo(): void
    {
        $this->logo = '';
        $this->site->forgetAttr('email.logo');
        $this->successMessage = 'Logo removed — emails fall back to the app logo.';
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

    public function resetTemplate(): void
    {
        $this->sections = EmailTemplate::defaultSections();
        $this->successMessage = 'Template reset to the default layout (not yet saved).';
    }

    public function save(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'forms.manage'), 403);
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'sections' => ['array'],
            'sections.*.key' => ['required', 'string'],
            'sections.*.text' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'string', 'max:2048'],
        ]);

        // Persist only the canonical shape (drops any stray keys).
        $clean = collect($this->sections)->map(fn ($s) => [
            'key' => $s['key'],
            'enabled' => (bool) ($s['enabled'] ?? true),
            'text' => in_array($s['key'], EmailTemplate::EDITABLE, true) ? ($s['text'] ?? null) : null,
        ])->values()->all();

        $this->site->setAttr('email.receipt_subject', trim($this->subject));
        $this->site->setAttr('email.receipt_sections', json_encode($clean));
        $this->logo === '' ? $this->site->forgetAttr('email.logo') : $this->site->setAttr('email.logo', $this->logo);

        $this->successMessage = 'Receipt email saved.';
    }

    /** Live preview: fill placeholders with sample data through the shared engine. */
    public function getPreviewProperty(): array
    {
        $sample = ['name' => 'Alex', 'email' => 'alex@example.com', 'phone' => '07700 900123', 'message' => 'Looks great — please get in touch.'];
        $ctx = ['name' => 'Alex', 'site' => ucwords(str_replace('-', ' ', $this->site->name)), 'type' => 'message'];

        $sections = collect($this->sections)
            ->filter(fn ($s) => $s['enabled'] ?? true)
            ->map(fn ($s) => [
                'key' => $s['key'],
                'label' => EmailTemplate::label($s['key']),
                'text' => ($s['text'] ?? null) !== null ? EmailTemplate::fill($s['text'], $ctx, $sample) : null,
            ])
            ->values()
            ->all();

        return [
            'subject' => EmailTemplate::fill($this->subject, $ctx, $sample),
            'sections' => $sections,
            'sample' => $sample,
        ];
    }

    public function render()
    {
        return view('livewire.site-emails-page', [
            'editableKeys' => EmailTemplate::EDITABLE,
            'labels' => collect($this->sections)->mapWithKeys(fn ($s) => [$s['key'] => EmailTemplate::label($s['key'])])->all(),
        ]);
    }
}
