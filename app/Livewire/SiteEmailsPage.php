<?php

namespace App\Livewire;

use App\Mail\SubmissionReceipt;
use App\Models\Site;
use App\Services\MediaStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Emails page — the site admin edits the branded receipt that every visitor
 * gets on a submission (forms, contact, interest, bookings): subject, body
 * (with {placeholders}) and the logo. Stored as site attributes; consumed by
 * the SubmissionReceipt mailable.
 */
class SiteEmailsPage extends Component
{
    use WithFileUploads;

    public Site $site;

    public string $subject = '';

    public string $body = '';

    public string $logo = '';        // stored URL

    public $logoUpload;              // transient upload

    public string $successMessage = '';

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'forms.manage'), 403);
        $this->site = $site;
        $this->subject = (string) $site->getAttr('email.receipt_subject', SubmissionReceipt::defaultSubject());
        $this->body = (string) $site->getAttr('email.receipt_body', SubmissionReceipt::defaultBody());
        $this->logo = (string) $site->getAttr('email.logo', '');
    }

    /** Uploaded logos land in the site's Asset library (so they're re-pickable). */
    public function updatedLogoUpload(): void
    {
        $this->validate(['logoUpload' => ['image', 'max:4096']]);
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
        $this->successMessage = 'Logo removed — emails will show the site name instead.';
    }

    public function save(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'forms.manage'), 403);
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'logo' => ['nullable', 'string', 'max:2048'],
        ]);

        $this->site->setAttr('email.receipt_subject', trim($this->subject));
        $this->site->setAttr('email.receipt_body', trim($this->body));
        $this->logo === '' ? $this->site->forgetAttr('email.logo') : $this->site->setAttr('email.logo', $this->logo);

        $this->successMessage = 'Receipt email saved.';
    }

    /** Live preview: fill placeholders with sample data. */
    public function getPreviewProperty(): array
    {
        $sampleFields = ['name' => 'Alex', 'email' => 'alex@example.com', 'phone' => '07700 900123', 'message' => 'Looks great — please get in touch.'];
        $map = [
            '{name}' => 'Alex',
            '{site}' => ucwords(str_replace('-', ' ', $this->site->name)),
            '{type}' => 'message',
        ];

        $fill = function (string $text) use ($map, $sampleFields) {
            $text = preg_replace_callback('/\{field:\s*([^}]+?)\s*\}/', fn ($m) => $sampleFields[strtolower(trim($m[1]))] ?? '«'.trim($m[1]).'»', $text);
            $text = str_replace('{fields}', collect($sampleFields)->map(fn ($v, $k) => Str::headline($k).': '.$v)->implode("\n"), $text);

            return strtr($text, $map);
        };

        return ['subject' => $fill($this->subject), 'body' => $fill($this->body)];
    }

    public function render()
    {
        return view('livewire.site-emails-page');
    }
}
