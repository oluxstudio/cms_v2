<?php

namespace App\Livewire;

use App\Models\Site;
use App\Services\MediaStore;
use App\Support\SiteProperties;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Site properties — the business identity templates read: display name,
 * logo, main email, labelled phone numbers and email addresses, and custom
 * variables the admin names (key · type · value). See SiteProperties.
 */
class SitePropertiesPage extends Component
{
    use WithFileUploads;

    public Site $site;

    public string $name = '';

    public string $logo = '';

    public string $email = '';

    /** @var list<array{label:string,value:string}> */
    public array $phones = [];

    /** @var list<array{label:string,value:string}> */
    public array $emails = [];

    /** @var list<array{key:string,type:string,value:string}> */
    public array $variables = [];

    public $logoUpload;

    /** Per-variable image uploads, keyed by row index. */
    public array $variableUploads = [];

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'properties.manage'), 403);
        $this->site = $site;
        $this->fill(SiteProperties::get($site));
        if ($this->name === '') {
            $this->name = ucwords(str_replace('-', ' ', $site->name));
        }
    }

    public function addPhone(): void
    {
        if (count($this->phones) < SiteProperties::MAX_ROWS) {
            $this->phones[] = ['label' => '', 'value' => ''];
        }
    }

    public function removePhone(int $i): void
    {
        unset($this->phones[$i]);
        $this->phones = array_values($this->phones);
    }

    public function addEmail(): void
    {
        if (count($this->emails) < SiteProperties::MAX_ROWS) {
            $this->emails[] = ['label' => '', 'value' => ''];
        }
    }

    public function removeEmail(int $i): void
    {
        unset($this->emails[$i]);
        $this->emails = array_values($this->emails);
    }

    public function addVariable(string $type = 'text'): void
    {
        if (count($this->variables) < SiteProperties::MAX_ROWS) {
            $this->variables[] = ['key' => '', 'type' => isset(SiteProperties::TYPES[$type]) ? $type : 'text', 'value' => ''];
        }
    }

    public function removeVariable(int $i): void
    {
        unset($this->variables[$i], $this->variableUploads[$i]);
        $this->variables = array_values($this->variables);
        $this->variableUploads = [];
    }

    public function moveVariable(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->variables[$i], $this->variables[$j])) {
            [$this->variables[$i], $this->variables[$j]] = [$this->variables[$j], $this->variables[$i]];
        }
    }

    /** Uploads land in the site's Assets library, so they can be re-picked anywhere. */
    public function updatedLogoUpload(): void
    {
        $this->validate(['logoUpload' => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif,svg', 'max:4096']]);
        $this->logo = app(MediaStore::class)->store($this->site, $this->logoUpload)->publicUrl();
        $this->logoUpload = null;
    }

    public function updatedVariableUploads($file, $index): void
    {
        $i = (int) $index;
        $this->validate(["variableUploads.$i" => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif,svg', 'max:8192']]);
        if (isset($this->variables[$i])) {
            $this->variables[$i]['value'] = app(MediaStore::class)->store($this->site, $file)->publicUrl();
            $this->variables[$i]['type'] = 'image';
        }
        unset($this->variableUploads[$i]);
    }

    public function save(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'properties.manage'), 403);

        // Rows left completely blank are dropped rather than rejected.
        $keep = fn (array $rows, array $fields) => array_values(array_filter(
            array_map(fn ($r) => array_map(fn ($v) => trim((string) $v), $r), $rows),
            fn ($r) => collect($fields)->contains(fn ($f) => ($r[$f] ?? '') !== ''),
        ));
        $this->phones = $keep($this->phones, ['label', 'value']);
        $this->emails = $keep($this->emails, ['label', 'value']);
        $this->variables = array_map(
            fn ($r) => ['key' => strtolower($r['key'] ?? ''), 'type' => $r['type'] ?? 'text', 'value' => $r['value'] ?? ''],
            $keep($this->variables, ['key', 'value']),
        );

        try {
            $this->validateProperties();
        } catch (ValidationException $e) {
            $first = array_key_first($e->errors()) ?? '';
            $this->dispatch('properties-error', tab: match (true) {
                str_starts_with($first, 'phones'), str_starts_with($first, 'emails') => 'contacts',
                str_starts_with($first, 'variable') => 'variables',
                default => 'identity',
            });
            throw $e;
        }

        SiteProperties::save($this->site, [
            'name' => $this->name,
            'logo' => $this->logo,
            'email' => $this->email,
            'phones' => $this->phones,
            'emails' => $this->emails,
            'variables' => $this->variables,
        ]);

        $this->dispatch('toast', level: 'success', title: 'Properties saved',
            message: 'Your site and its templates now use these details.');
    }

    private function validateProperties(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'email' => ['nullable', 'email', 'max:190'],
            'phones' => ['array', 'max:'.SiteProperties::MAX_ROWS],
            'phones.*.label' => ['nullable', 'string', 'max:60'],
            'phones.*.value' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\-\s\/ext]{3,40}$/i'],
            'emails' => ['array', 'max:'.SiteProperties::MAX_ROWS],
            'emails.*.label' => ['nullable', 'string', 'max:60'],
            'emails.*.value' => ['required', 'email', 'max:190'],
            'variables' => ['array', 'max:'.SiteProperties::MAX_ROWS],
            'variables.*.key' => ['required', 'regex:'.SiteProperties::KEY_PATTERN, 'distinct'],
            'variables.*.type' => ['required', Rule::in(array_keys(SiteProperties::TYPES))],
            'variables.*.value' => ['nullable', 'string', 'max:10000'],
        ], [
            'phones.*.value.required' => 'Enter the number, or remove the row.',
            'phones.*.value.regex' => 'Use digits, spaces and + ( ) - only.',
            'emails.*.value.required' => 'Enter the email address, or remove the row.',
            'emails.*.value.email' => 'That doesn\'t look like an email address.',
            'variables.*.key.required' => 'Give the variable a name.',
            'variables.*.key.regex' => 'Lowercase letters, numbers and _ only, starting with a letter.',
            'variables.*.key.distinct' => 'This name is used twice.',
        ]);
    }

    public function render()
    {
        return view('livewire.site-properties-page', ['types' => SiteProperties::TYPES]);
    }
}
