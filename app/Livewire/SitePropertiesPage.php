<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitReached;
use App\Models\Site;
use App\Services\MediaStore;
use App\Support\PropertyLinks;
use App\Support\SiteColors;
use App\Support\SiteProperties;
use App\Support\SiteTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Site properties — the business profile templates read (see SiteProperties
 * and config/site-properties.php). Every field is a node of the site's
 * "Site Properties" component, so edits here and on the Edit page are the
 * same data. Tabs, fields and validation all come from the schema.
 */
class SitePropertiesPage extends Component
{
    use WithFileUploads;

    public Site $site;

    /** @var array<string,string> field key => value */
    public array $values = [];

    /** @var array<string, list<array<string,string>>> repeater key => rows */
    public array $rows = [];

    /** @var list<array{key:string,type:string,value:string}> */
    public array $variables = [];

    /** @var array<string,string> template colour variable (color-primary …) => value */
    public array $colors = [];

    /** property => where an empty field was filled from (template content). */
    public array $filledFrom = [];

    /** property => places in the site's content it also updates. */
    public array $linkedPlaces = [];

    public string $currency = 'gbp';

    /** Custom scripts — premium plans, owners/admins only; never in the content payload. */
    public array $scripts = ['head' => '', 'body' => ''];

    /** Transient uploads: fields.{key}, rows.{repeater}.{i}.{sub}, variables.{i} */
    public array $uploads = [];

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'properties.manage'), 403);
        $this->site = $site;
        $this->load();
    }

    private function load(): void
    {
        $component = SiteProperties::component($this->site);
        ['values' => $this->values, 'rows' => $this->rows, 'variables' => $this->variables] = SiteProperties::get($this->site, $component);

        // The template's own content (contact rows, profile entry, header fields) fills empty properties.
        $slots = PropertyLinks::discover($this->site);
        $this->linkedPlaces = PropertyLinks::places($slots);
        $this->filledFrom = [];
        foreach (PropertyLinks::fills($this->site, $this->linkedValues(), $slots) as $prop => $fill) {
            if ($prop === 'phone') {
                $this->rows['phones'] = [['label' => 'Main', 'value' => $fill['value']]];
            } else {
                $this->values[$prop] = $fill['value'];
            }
            $this->filledFrom[$prop] = $fill['where'];
        }
        if ($this->values['site_name'] === '') {
            $this->values['site_name'] = ucwords(str_replace('-', ' ', $this->site->name));
        }
        $this->currency = strtolower((string) ($this->site->currency ?: 'gbp'));
        $this->colors = SiteColors::current($this->site);
        $this->scripts = [
            'head' => (string) $this->site->getAttr(config('site-properties.scripts.head'), ''),
            'body' => (string) $this->site->getAttr(config('site-properties.scripts.body'), ''),
        ];
    }

    /** The linked properties as one map (phone = the first phone number). */
    private function linkedValues(?array $values = null, ?array $rows = null): array
    {
        $values ??= $this->values;
        $rows ??= $this->rows;
        $out = array_intersect_key($values, PropertyLinks::PROPS);
        $out['phone'] = (string) ($rows['phones'][0]['value'] ?? '');
        $out['hours'] = SiteTokens::hoursSummary($values);

        return $out;
    }

    /** Custom scripts: premium plans, and only the account owner / team admins. */
    public function getCanEditScriptsProperty(): bool
    {
        return (bool) $this->site->user?->currentSubscription()->allowsPremium()
            && $this->site->canManageTeam(Auth::user());
    }

    // ── Rows & variables ───────────────────────────────────────────

    public function addRow(string $key): void
    {
        $r = SiteProperties::repeaters()[$key] ?? null;
        if ($r && count($this->rows[$key] ?? []) < SiteProperties::MAX_ROWS) {
            $this->rows[$key][] = array_fill_keys(array_keys($r['fields']), '');
        }
    }

    public function removeRow(string $key, int $i): void
    {
        unset($this->rows[$key][$i]);
        $this->rows[$key] = array_values($this->rows[$key] ?? []);
        $this->uploads = [];
    }

    public function addVariable(string $type = 'text'): void
    {
        if (count($this->variables) < SiteProperties::MAX_ROWS) {
            $this->variables[] = ['key' => '', 'type' => isset(SiteProperties::TYPES[$type]) ? $type : 'text', 'value' => ''];
        }
    }

    public function removeVariable(int $i): void
    {
        unset($this->variables[$i]);
        $this->variables = array_values($this->variables);
        $this->uploads = [];
    }

    public function moveVariable(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->variables[$i], $this->variables[$j])) {
            [$this->variables[$i], $this->variables[$j]] = [$this->variables[$j], $this->variables[$i]];
        }
    }

    /** Copy Monday's hours to Tuesday–Friday. */
    public function copyHoursToWeekdays(): void
    {
        foreach (['tuesday', 'wednesday', 'thursday', 'friday'] as $d) {
            $this->values["hours_{$d}"] = $this->values['hours_monday'] ?? '';
        }
    }

    /** Copy the trading address into the registered office box. */
    public function copyAddressToOffice(): void
    {
        $this->values['registered_office'] = collect(['address_street', 'address_town', 'address_county', 'address_postcode', 'address_country'])
            ->map(fn ($k) => trim($this->values[$k] ?? ''))->filter()->implode(', ');
    }

    // ── Uploads (land in the site's Assets library) ────────────────

    public function updatedUploads($file, string $path): void
    {
        $this->validate(["uploads.$path" => ['file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:8192']]);
        try {
            $url = app(MediaStore::class)->store($this->site, $file)->publicUrl();
        } catch (PlanLimitReached $e) {
            $this->dispatch('upgrade-required', reason: $e->getMessage(), cta: $e->cta);

            return;
        }

        $parts = explode('.', $path);
        match ($parts[0]) {
            'fields' => $this->values[$parts[1]] = $url,
            'rows' => $this->rows[$parts[1]][(int) $parts[2]][$parts[3]] = $url,
            'variables' => $this->variables[(int) $parts[1]] = ['type' => 'image', 'value' => $url] + $this->variables[(int) $parts[1]],
            default => null,
        };
        data_forget($this->uploads, $path);
    }

    // ── Save ───────────────────────────────────────────────────────

    public function save(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'properties.manage'), 403);

        // Rows / variables left completely blank are dropped rather than rejected.
        $blankRow = fn (array $r) => collect($r)->every(fn ($v) => trim((string) $v) === '');
        foreach ($this->rows as $key => $list) {
            $this->rows[$key] = array_values(array_filter($list, fn ($r) => ! $blankRow($r)));
        }
        $this->variables = array_values(array_filter(
            array_map(fn ($v) => ['key' => trim($v['key'] ?? ''), 'type' => $v['type'] ?? 'text', 'value' => trim((string) ($v['value'] ?? ''))], $this->variables),
            fn ($v) => $v['key'] !== '' || $v['value'] !== '',
        ));

        try {
            $this->validate($this->rules(), $this->messages());
        } catch (ValidationException $e) {
            $this->dispatch('properties-error', tab: $this->tabForError(array_key_first($e->errors()) ?? ''));
            throw $e;
        }

        $oldIcon = SiteProperties::value($this->site, 'square_icon');
        $before = SiteProperties::get($this->site);
        SiteProperties::save($this->site, ['values' => $this->values, 'rows' => $this->rows, 'variables' => $this->variables], Auth::user()?->name);
        // …and into the template's own content (contact rows, profile entry, header fields) — added where missing.
        $synced = PropertyLinks::push($this->site, $this->linkedValues($before['values'], $before['rows']), $this->linkedValues(), Auth::user()?->name);
        if ($synced !== []) {
            SiteProperties::republish($this->site);
        }
        $this->site->update(['currency' => strtolower($this->currency)]);
        SiteColors::save($this->site, $this->colors);

        if ($this->canEditScripts) {
            foreach (config('site-properties.scripts') as $slot => $attr) {
                trim($this->scripts[$slot]) === '' ? $this->site->forgetAttr($attr) : $this->site->setAttr($attr, $this->scripts[$slot]);
            }
        }

        $made = SiteProperties::afterSave($this->site, $oldIcon);
        $iconNote = match ($made) {
            true => ' Favicon and app icons were created from your square icon.',
            false => ' The square icon couldn\'t be turned into icons — upload a square PNG or JPG of at least 192×192.',
            default => '',
        };

        $this->dispatch('toast', level: 'success', title: 'Properties saved',
            message: 'Your site, its templates and the Edit page now use these details.'
                .($synced !== [] ? ' Also updated: '.implode(', ', $synced).'.' : '').$iconNote);
        $this->load();
    }

    /** Validation rules, generated from the schema. */
    private function rules(): array
    {
        $rules = [
            'currency' => ['required', Rule::in(['gbp', 'eur', 'usd', 'cad', 'aud', 'ngn', 'zar', 'ghs', 'kes', 'inr'])],
            'variables' => ['array', 'max:'.SiteProperties::MAX_ROWS],
            'variables.*.key' => ['required', 'regex:'.SiteProperties::KEY_PATTERN, 'distinct',
                fn ($attr, $value, $fail) => SiteProperties::isSchemaLabel((string) $value) ? $fail('That name is already used by a built-in property.') : null],
            'variables.*.type' => ['required', Rule::in(array_keys(SiteProperties::TYPES))],
            'variables.*.value' => ['nullable', 'string', 'max:10000'],
            'colors.*' => ['nullable', 'string', 'max:60', 'regex:'.SiteColors::PATTERN],
            'scripts.head' => ['nullable', 'string', 'max:20000'],
            'scripts.body' => ['nullable', 'string', 'max:20000'],
        ];
        foreach (SiteProperties::fields() as $key => $f) {
            $rules["values.$key"] = $this->rulesFor($f);
        }
        $rules['values.site_name'][] = fn ($attr, $value, $fail) => SiteProperties::renameTaken($this->site, (string) $value)
            ? $fail('Another site is already called that. Pick a different name.') : null;
        foreach (SiteProperties::repeaters() as $key => $r) {
            $rules["rows.$key"] = ['array', 'max:'.SiteProperties::MAX_ROWS];
            foreach ($r['fields'] as $sub => $f) {
                $rules["rows.$key.*.$sub"] = $this->rulesFor($f, (bool) ($f['required'] ?? false));
            }
        }

        return $rules;
    }

    private function rulesFor(array $f, bool $required = false): array
    {
        $given = $f['rules'] ?? [];
        $base = match ($f['input']) {
            'url' => ['url:http,https', 'max:2048'],
            'email' => ['email', 'max:190'],
            'number' => ['numeric'],
            'date' => ['date'],
            'toggle' => [Rule::in(['0', '1', ''])],
            'hours' => ['regex:'.SiteProperties::HOURS_PATTERN],
            'select' => [Rule::in(array_merge([''], array_map('strval', array_keys($f['options'] ?? []))))],
            'image' => ['max:2048'],
            default => ['max:5000'],
        };
        $lead = ($required || in_array('required', $given, true)) ? ['required'] : ['nullable'];

        return array_values(array_unique(array_merge($lead, ['string'], array_diff($given, ['required', 'nullable']), $base), SORT_REGULAR));
    }

    private function messages(): array
    {
        return [
            'values.*.regex' => 'That doesn\'t look right — check the format.',
            'values.hours_*.regex' => 'Use "Closed", or times like 09:00-17:00 (add more ranges with commas: 09:00-12:00, 13:00-17:00).',
            'values.*.url' => 'Enter a full web address starting with https://',
            'values.site_name.required' => 'Your site needs a name.',
            'rows.*.*.value.required' => 'Fill this in, or remove the row.',
            'rows.*.*.value.regex' => 'Use digits, spaces and + ( ) - only.',
            'rows.*.*.date.required' => 'Pick the date, or remove the row.',
            'variables.*.key.required' => 'Give the variable a name.',
            'variables.*.key.regex' => 'Start with a letter; letters, numbers, spaces, _ and - only.',
            'colors.*.regex' => 'Use a colour like #ec0470, rgb(236, 4, 112) or hsl(330, 97%, 47%).',
            'variables.*.key.distinct' => 'This name is used twice.',
        ];
    }

    private function tabForError(string $key): string
    {
        $parts = explode('.', $key);

        return match ($parts[0]) {
            'values' => SiteProperties::fields()[$parts[1] ?? '']['tab'] ?? 'brand',
            'rows' => SiteProperties::repeaters()[$parts[1] ?? '']['tab'] ?? 'contact',
            'variables' => 'variables',
            'colors' => 'colours',
            'scripts' => 'seo',
            'currency' => 'locale',
            default => 'brand',
        };
    }

    public function render()
    {
        return view('livewire.site-properties-page', [
            'tokens' => SiteTokens::all($this->site),
            'fields' => SiteProperties::fields(),
            'repeaters' => SiteProperties::repeaters(),
            'tabs' => config('site-properties.tabs'),
            'types' => SiteProperties::TYPES,
            'icons' => json_decode((string) $this->site->getAttr(config('site-properties.icons_attr')), true) ?: [],
        ]);
    }
}
