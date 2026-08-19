<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Support\EmailTemplate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class SiteFormsPage extends Component
{
    use WithPagination;

    public Site $site;

    /**
     * Page mode:
     *   list      – card grid of all forms
     *   form      – create (activeFormId = null) or edit (activeFormId = id)
     *   detail    – read-only overview of a form (fields, description, validations)
     *   responses – paginated response accordion for a form
     */
    public string $mode = 'list';

    /** ID of the Form being viewed / edited. null = creating a new form. */
    public ?string $activeFormId = null;

    // ─────────────────────────────────────────────────────────────
    // Form-builder state
    // ─────────────────────────────────────────────────────────────

    public string $fbTitle = '';

    public string $fbName = '';

    public string $fbDescription = '';

    public bool $fbIsActive = true;

    /**
     * Array of field-draft objects used in the builder UI:
     * [
     *   [
     *     'key'         => 'email',          // auto-slugged from label
     *     'label'       => 'Email Address',
     *     'type'        => 'email',
     *     'required'    => true,
     *     'placeholder' => 'you@example.com',
     *     'min'         => '',
     *     'max'         => '255',
     *     'options'     => '',               // comma-separated string for select/radio
     *   ],
     *   …
     * ]
     */
    public array $fbFields = [];

    /**
     * Delivery config draft (deliveryConfig() shape): channels → per-channel
     * settings. Email is editable; sms/whatsapp are shown disabled until built.
     */
    public array $fbDelivery = [];

    /**
     * Per-form receipt template draft: ['customized'=>bool, 'subject'=>string,
     * 'sections'=>[...]]. Seeded from the site default; when `customized` is
     * false the form uses the site-wide template.
     */
    public array $fbTemplate = [];

    // ─────────────────────────────────────────────────────────────
    // Response-view state
    // ─────────────────────────────────────────────────────────────

    public ?string $openId = null;

    // ─────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────

    public function mount(Site $site, ?string $openResponse = null): void
    {
        $this->site = $site;

        // Deep link from the admin submission-alert email: jump to the specific
        // response, open it, and mark it read.
        if ($openResponse) {
            $response = FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $site->id))
                ->find($openResponse);
            if ($response) {
                $this->activeFormId = $response->form_id;
                $this->mode = 'responses';
                $this->openId = $response->id;
                $response->markAsRead();
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Lifecycle hooks for auto-slug
    // ─────────────────────────────────────────────────────────────

    /**
     * Fallback slug generation on blur (Alpine handles the live version).
     * Only fires when the slug field is still completely empty — e.g. if
     * the user somehow bypassed the Alpine handler.
     */
    public function updatedFbTitle(string $value): void
    {
        if (! $this->activeFormId && trim($this->fbName) === '') {
            $this->fbName = Str::slug($value, '-');
        }
    }

    /** Auto-fill key from label when key is still empty. */
    public function updatedFbFields(mixed $value, string $path): void
    {
        // path looks like "0.label", "1.type", etc.
        if (str_ends_with($path, '.label')) {
            $index = (int) explode('.', $path)[0];
            if (isset($this->fbFields[$index]) && empty($this->fbFields[$index]['key'])) {
                $this->fbFields[$index]['key'] = Str::snake(
                    preg_replace('/[^a-zA-Z0-9\s]/', '', (string) $value)
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Navigation
    // ─────────────────────────────────────────────────────────────

    public function goCreate(): void
    {
        $this->resetFormBuilder();
        $this->activeFormId = null;
        $this->mode = 'form';
    }

    public function goEdit(string $id): void
    {
        $form = $this->guardedForm($id);
        if (! $form) {
            return;
        }

        $this->activeFormId = $id;
        $this->loadFormIntoBuilder($form);
        $this->mode = 'form';
    }

    public function goDetail(string $id): void
    {
        $form = $this->guardedForm($id);
        if (! $form) {
            return;
        }

        $this->activeFormId = $id;
        $this->openId = null;
        $this->mode = 'detail';
    }

    public function goResponses(?string $id = null): void
    {
        $formId = $id ?? $this->activeFormId;
        if (! $formId || ! $this->guardedForm($formId)) {
            return;
        }

        $this->activeFormId = $formId;
        $this->openId = null;
        $this->resetPage();
        $this->mode = 'responses';
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->activeFormId = null;
        $this->openId = null;
        $this->resetPage();
        $this->resetFormBuilder();
    }

    public function backToDetail(): void
    {
        $this->openId = null;
        $this->resetPage();
        $this->mode = 'detail';
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD — forms
    // ─────────────────────────────────────────────────────────────

    public function saveForm(): void
    {
        $this->validate([
            'fbTitle' => ['required', 'string', 'max:255'],
            'fbName' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9\-_]*$/'],
            'fbDescription' => ['nullable', 'string', 'max:2000'],
            'fbFields' => ['array'],
            'fbFields.*.label' => ['required', 'string', 'max:100'],
            'fbFields.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fbFields.*.type' => ['required', 'in:text,email,tel,number,url,date,textarea,select,radio,checkbox'],
            'fbFields.*.min' => ['nullable', 'string', 'max:20'],
            'fbFields.*.max' => ['nullable', 'string', 'max:20'],
            'fbFields.*.options' => ['nullable', 'string', 'max:500'],
        ], [
            'fbTitle.required' => 'A form title is required.',
            'fbName.required' => 'A form slug is required.',
            'fbName.regex' => 'Slug may only contain lowercase letters, numbers, hyphens and underscores.',
            'fbFields.*.label.required' => 'Every field needs a label.',
            'fbFields.*.key.required' => 'Every field needs a key.',
            'fbFields.*.key.regex' => 'Field keys must start with a letter and contain only lowercase letters, numbers and underscores.',
            'fbFields.*.type.required' => 'Please choose a type for each field.',
            'fbFields.*.type.in' => 'Invalid field type.',
        ]);

        // Check slug uniqueness within this site
        $slugQuery = Form::where('site_id', $this->site->id)
            ->where('name', $this->fbName);

        if ($this->activeFormId) {
            $slugQuery->where('id', '!=', $this->activeFormId);
        }

        if ($slugQuery->exists()) {
            $this->addError('fbName', 'A form with this slug already exists on this site.');

            return;
        }

        $payload = [
            'title' => trim($this->fbTitle),
            'name' => $this->fbName,
            'description' => trim($this->fbDescription) ?: null,
            'is_active' => $this->fbIsActive,
            'fields' => $this->prepareFieldsForSave($this->fbFields),
            'delivery' => $this->prepareDeliveryForSave(),
            'email_template' => $this->prepareTemplateForSave(),
        ];

        if ($this->activeFormId) {
            Form::where('site_id', $this->site->id)
                ->find($this->activeFormId)
                ?->update($payload);
        } else {
            $created = Form::create(['site_id' => $this->site->id] + $payload);
            $this->activeFormId = $created->id;
        }

        $this->mode = 'detail';
    }

    public function deleteForm(string $id): void
    {
        Form::where('site_id', $this->site->id)->find($id)?->delete();

        if ($this->activeFormId === $id) {
            $this->backToList();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD — field builder
    // ─────────────────────────────────────────────────────────────

    public function addField(): void
    {
        $this->fbFields[] = [
            'key' => '',
            'label' => '',
            'type' => 'text',
            'required' => false,
            'placeholder' => '',
            'min' => '',
            'max' => '',
            'options' => '',
        ];
    }

    public function removeField(int $index): void
    {
        array_splice($this->fbFields, $index, 1);
        $this->fbFields = array_values($this->fbFields);
    }

    public function moveFieldUp(int $index): void
    {
        if ($index === 0) {
            return;
        }
        [$this->fbFields[$index - 1], $this->fbFields[$index]] =
            [$this->fbFields[$index], $this->fbFields[$index - 1]];
    }

    public function moveFieldDown(int $index): void
    {
        if ($index >= count($this->fbFields) - 1) {
            return;
        }
        [$this->fbFields[$index], $this->fbFields[$index + 1]] =
            [$this->fbFields[$index + 1], $this->fbFields[$index]];
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD — responses
    // ─────────────────────────────────────────────────────────────

    /** Jump from the "Recent responses" rail straight into a response (marks it read). */
    public function openResponse(string $id): void
    {
        $response = FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))->find($id);
        if (! $response) {
            return;
        }
        $this->activeFormId = $response->form_id;
        $this->mode = 'responses';
        $this->openId = $response->id;
        $this->resetPage();
        $response->markAsRead();
    }

    public function toggleOpen(string $id): void
    {
        $this->openId = ($this->openId === $id) ? null : $id;

        if ($this->openId !== null) {
            $this->guardedResponse($id)?->markAsRead();
        }
    }

    public function markAllRead(): void
    {
        if (! $this->activeFormId) {
            return;
        }

        FormResponse::where('form_id', $this->activeFormId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function deleteResponse(string $id): void
    {
        $this->guardedResponse($id)?->delete();
        if ($this->openId === $id) {
            $this->openId = null;
        }
    }

    /**
     * Turn a form response into a CRM contact.
     * Dedupes by email within the site — an existing contact is updated and
     * linked, otherwise a fresh contact is created.
     */
    public function convertToContact(string $id): void
    {
        $response = $this->guardedResponse($id);
        if (! $response || $response->contact_id) {
            return; // missing or already converted
        }

        $lead = $response->extractContactData();

        $contact = null;
        if (! empty($lead['email'])) {
            $contact = Contact::where('site_id', $this->site->id)
                ->where('email', $lead['email'])
                ->first();
        }

        if ($contact) {
            // Enrich the existing contact without clobbering good data
            $contact->fill([
                'name' => $contact->name ?: $lead['name'],
                'phone' => $contact->phone ?: $lead['phone'],
                'data' => array_merge($contact->data ?? [], $lead['extra']),
                'last_activity_at' => now(),
            ])->save();
        } else {
            $contact = Contact::create([
                'site_id' => $this->site->id,
                'name' => $lead['name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'status' => 'new',
                'source_form_id' => $response->form_id,
                'data' => $lead['extra'],
                'last_activity_at' => now(),
            ]);
            $contact->logActivity('created');
        }

        $response->update([
            'contact_id' => $contact->id,
            'converted_at' => now(),
            'read_at' => $response->read_at ?? now(),
        ]);

        // Record the submission on the contact's timeline
        $contact->logActivity('form_submitted', null, [
            'response_id' => $response->id,
            'form_id' => $response->form_id,
        ]);

        session()->flash('contact_converted', $contact->name);
    }

    // ─────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────

    public function render()
    {
        $forms = Form::where('site_id', $this->site->id)
            ->withCount([
                'responses',
                'responses as unread_count' => fn ($q) => $q->whereNull('read_at'),
            ])
            ->latest()
            ->get()
            ->map(function (Form $form) {
                $form->last_at = $form->responses()->latest()->value('created_at');

                return $form;
            })
            // Forms with NEW responses surface first, then by latest activity.
            ->sortByDesc(fn (Form $f) => [$f->unread_count > 0, (string) $f->last_at, (string) $f->created_at])
            ->values();

        // Site-wide latest submissions for the list view's "Recent responses" rail.
        $recentResponses = $this->mode === 'list'
            ? FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $this->site->id))
                ->with('form:id,title,name')
                ->latest()->take(8)->get()
            : collect();

        $activeForm = null;
        if ($this->activeFormId && in_array($this->mode, ['detail', 'responses', 'form'])) {
            $activeForm = Form::where('site_id', $this->site->id)
                ->withCount([
                    'responses',
                    'responses as unread_count' => fn ($q) => $q->whereNull('read_at'),
                ])
                ->find($this->activeFormId);
        }

        $responses = collect();
        if ($this->mode === 'responses' && $this->activeFormId) {
            $responses = FormResponse::where('form_id', $this->activeFormId)
                ->with('contact:id,name,status')
                ->latest()
                ->paginate(25);
        }

        $channels = config('form_channels.channels', []);
        $tplLabels = collect($this->fbTemplate['sections'] ?? [])->mapWithKeys(fn ($s) => [$s['key'] => EmailTemplate::label($s['key'])])->all();
        $editableKeys = EmailTemplate::EDITABLE;
        $siteLogo = (string) $this->site->getAttr('email.logo', '');

        return view('livewire.site-forms-page', compact('forms', 'activeForm', 'responses', 'recentResponses', 'channels', 'tplLabels', 'editableKeys', 'siteLogo'));
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function guardedForm(string $id): ?Form
    {
        return Form::where('site_id', $this->site->id)->find($id);
    }

    private function guardedResponse(string $id): ?FormResponse
    {
        if (! $this->activeFormId) {
            return null;
        }

        return FormResponse::where('form_id', $this->activeFormId)->find($id);
    }

    private function resetFormBuilder(): void
    {
        $this->fbTitle = '';
        $this->fbName = '';
        $this->fbDescription = '';
        $this->fbIsActive = true;
        $this->fbFields = [];
        $this->fbDelivery = $this->hydrateDelivery(Form::defaultDelivery());
        $this->fbTemplate = $this->seedTemplate(null);
        $this->resetErrorBag();
    }

    /**
     * Build the template draft: a customised form template when present, else
     * the site default (pre-filled so the admin can start editing straight away).
     */
    private function seedTemplate(?Form $form): array
    {
        $tpl = $form?->email_template;
        if (is_array($tpl) && ! empty($tpl['customized'])) {
            return [
                'customized' => true,
                'subject' => (string) ($tpl['subject'] ?? EmailTemplate::defaultSubject()),
                'sections' => EmailTemplate::resolveSections($tpl['sections'] ?? null),
            ];
        }

        $default = EmailTemplate::siteDefault($this->site);

        return [
            'customized' => false,
            'subject' => $default['subject'],
            'sections' => $default['sections'],
        ];
    }

    public function moveTplSectionUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->fbTemplate['sections'][$index])) {
            return;
        }
        [$this->fbTemplate['sections'][$index - 1], $this->fbTemplate['sections'][$index]]
            = [$this->fbTemplate['sections'][$index], $this->fbTemplate['sections'][$index - 1]];
    }

    public function moveTplSectionDown(int $index): void
    {
        if ($index >= count($this->fbTemplate['sections']) - 1) {
            return;
        }
        [$this->fbTemplate['sections'][$index], $this->fbTemplate['sections'][$index + 1]]
            = [$this->fbTemplate['sections'][$index + 1], $this->fbTemplate['sections'][$index]];
    }

    /** Re-seed the template draft from the current site default. */
    public function resetTemplateToSiteDefault(): void
    {
        $default = EmailTemplate::siteDefault($this->site);
        $this->fbTemplate['subject'] = $default['subject'];
        $this->fbTemplate['sections'] = $default['sections'];
    }

    /** Store null when not customised (→ site default), else the cleaned template. */
    private function prepareTemplateForSave(): ?array
    {
        if (empty($this->fbTemplate['customized'])) {
            return null;
        }

        $sections = collect($this->fbTemplate['sections'] ?? [])->map(fn ($s) => [
            'key' => $s['key'],
            'enabled' => (bool) ($s['enabled'] ?? true),
            'text' => in_array($s['key'], EmailTemplate::EDITABLE, true) ? ($s['text'] ?? null) : null,
        ])->values()->all();

        return [
            'customized' => true,
            'subject' => trim((string) ($this->fbTemplate['subject'] ?? '')) ?: EmailTemplate::defaultSubject(),
            'sections' => $sections,
        ];
    }

    /** Live preview of the form's receipt with sample data. */
    public function getTemplatePreviewProperty(): array
    {
        $sample = ['name' => 'Alex', 'email' => 'alex@example.com', 'phone' => '07700 900123', 'message' => 'Looks great — please get in touch.'];
        $ctx = ['name' => 'Alex', 'site' => ucwords(str_replace('-', ' ', $this->site->name)), 'type' => ($this->fbTitle ?: 'form').' form'];

        $sections = collect($this->fbTemplate['sections'] ?? [])
            ->filter(fn ($s) => $s['enabled'] ?? true)
            ->map(fn ($s) => [
                'key' => $s['key'],
                'label' => EmailTemplate::label($s['key']),
                'text' => ($s['text'] ?? null) !== null ? EmailTemplate::fill($s['text'], $ctx, $sample) : null,
            ])
            ->values()
            ->all();

        return [
            'subject' => EmailTemplate::fill((string) ($this->fbTemplate['subject'] ?? ''), $ctx, $sample),
            'sections' => $sections,
            'sample' => $sample,
        ];
    }

    /** Merge a stored/default delivery config into a UI-complete draft (every registry channel present). */
    private function hydrateDelivery(array $config): array
    {
        $channels = $config['channels'] ?? [];
        $email = array_merge(Form::defaultDelivery()['channels']['email'], $channels['email'] ?? []);
        $out = ['channels' => ['email' => $email]];
        foreach (array_keys(config('form_channels.channels', [])) as $key) {
            if ($key === 'email') {
                continue;
            }
            $out['channels'][$key] = array_merge(['enabled' => false], $channels[$key] ?? []);
        }

        return $out;
    }

    /** Normalise the draft for storage: trim the admin address to null when blank. */
    private function prepareDeliveryForSave(): array
    {
        $out = $this->hydrateDelivery($this->fbDelivery);
        $addr = trim((string) ($out['channels']['email']['admin_address'] ?? ''));
        $out['channels']['email']['admin_address'] = $addr !== '' ? $addr : null;

        return $out;
    }

    private function loadFormIntoBuilder(Form $form): void
    {
        $this->fbTitle = $form->title ?? '';
        $this->fbName = $form->name;
        $this->fbDescription = $form->description ?? '';
        $this->fbIsActive = $form->is_active;

        $this->fbFields = collect($form->fields ?? [])->map(fn ($f) => [
            'key' => $f['key'] ?? '',
            'label' => $f['label'] ?? '',
            'type' => $f['type'] ?? 'text',
            'required' => (bool) ($f['required'] ?? false),
            'placeholder' => $f['placeholder'] ?? '',
            'min' => $f['min'] ?? '',
            'max' => $f['max'] ?? '',
            // Convert stored array back to comma-separated string for the UI
            'options' => is_array($f['options'] ?? null)
                ? implode(', ', $f['options'])
                : ($f['options'] ?? ''),
        ])->toArray();

        $this->fbDelivery = $this->hydrateDelivery($form->deliveryConfig());
        $this->fbTemplate = $this->seedTemplate($form);

        $this->resetErrorBag();
    }

    /** Convert builder state → DB-ready field-definition array. */
    private function prepareFieldsForSave(array $fbFields): array
    {
        return collect($fbFields)->map(fn ($f) => [
            'key' => $f['key'],
            'label' => $f['label'],
            'type' => $f['type'],
            'required' => (bool) ($f['required'] ?? false),
            'placeholder' => ($f['placeholder'] ?? '') ?: null,
            'min' => ($f['min'] ?? '') !== '' ? $f['min'] : null,
            'max' => ($f['max'] ?? '') !== '' ? $f['max'] : null,
            // Split comma-separated options into a clean array
            'options' => ($f['options'] ?? '') !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $f['options']))))
                : [],
        ])->values()->toArray();
    }
}
