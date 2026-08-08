<?php

namespace App\Livewire;

use App\Models\Estimate;
use App\Models\Estimator;
use App\Models\EstimatorCalc;
use App\Models\EstimatorField;
use App\Models\Site;
use App\Services\Estimator\Formula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Estimates admin — the site's NAMED ESTIMATORS (Cleaner, Mover, …): each one
 * is created by name, then gets its own fields, calculator-built formulas and
 * customer email template. Below them, the captured leads pipeline.
 */
class EstimatesPage extends Component
{
    public const STATUSES = ['new', 'contacted', 'won', 'lost'];

    public Site $site;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    // ── Estimators ──
    public string $newEstimatorName = '';

    public ?string $selectedId = null;       // estimator open in the editor

    // ── Editor: name + email template ──
    public string $eName = '';

    public string $eEmailSubject = '';

    public string $eEmailBody = '';

    // ── Field form ──
    public ?string $fieldEditingId = null;   // 0 = new

    public string $fLabel = '';

    public string $fType = 'number';

    public string $fOptions = '';         // one per line: "Label = 12.5"

    public string $fUnit = '';

    public string $fValue = '';           // fixed set-data value

    public bool $fRequired = false;

    // ── Calculation form (calculator-built) ──
    public ?string $calcEditingId = null;    // 0 = new

    public string $cName = '';

    public string $cFormula = '';

    public string $cFormat = 'money';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    private function guardManage(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'estimates.manage'), 403);
    }

    public function getCanManageProperty(): bool
    {
        return $this->site->allows(Auth::user(), 'estimates.manage');
    }

    // ═══ Estimators ═══

    public function getEstimatorsProperty()
    {
        return $this->site->estimators()->withCount(['fields', 'calcs', 'estimates'])->get();
    }

    public function getSelectedProperty(): ?Estimator
    {
        return $this->selectedId ? $this->site->estimators()->find($this->selectedId) : null;
    }

    public function createEstimator(): void
    {
        $this->guardManage();
        $this->validate(['newEstimatorName' => ['required', 'string', 'max:80']]);

        $name = trim($this->newEstimatorName);
        $estimator = $this->site->estimators()->create([
            'name' => $name,
            'slug' => Estimator::slugFor($this->site->id, $name),
            'sort' => $this->estimators->count(),
        ]);

        $this->reset('newEstimatorName');
        $this->dispatch('toast', level: 'success', title: 'Estimator created', message: $name.' — now add its fields and calculations.');
        $this->select($estimator->id);
    }

    public function select(string $id): void
    {
        $this->guardManage();
        $estimator = $this->site->estimators()->findOrFail($id);
        $this->selectedId = $id;
        $this->eName = $estimator->name;
        $this->eEmailSubject = $estimator->emailSubject();
        $this->eEmailBody = $estimator->emailBody();
        $this->closeField();
        $this->closeCalc();
        $this->errorMessage = '';
    }

    public function closeEditor(): void
    {
        $this->reset(['selectedId', 'eName', 'eEmailSubject', 'eEmailBody']);
        $this->closeField();
        $this->closeCalc();
    }

    public function saveEstimatorSettings(): void
    {
        $this->guardManage();
        $this->validate([
            'eName' => ['required', 'string', 'max:80'],
            'eEmailSubject' => ['required', 'string', 'max:150'],
            'eEmailBody' => ['required', 'string', 'max:5000'],
        ]);
        $this->selected?->update([
            'name' => trim($this->eName),
            'email_subject' => $this->eEmailSubject,
            'email_body' => $this->eEmailBody,
        ]);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Estimator settings and email template updated.');
    }

    public function deleteEstimator(string $id): void
    {
        $this->guardManage();
        $estimator = $this->site->estimators()->findOrFail($id);
        $estimator->delete(); // fields/calcs cascade; estimates keep their data
        if ($this->selectedId === $id) {
            $this->closeEditor();
        }
        $this->dispatch('toast', level: 'success', title: 'Estimator deleted', message: $estimator->name.' was removed.');
    }

    // ═══ Fields (inside the selected estimator) ═══

    public function getFieldsProperty()
    {
        return $this->selected?->fields()->get() ?? collect();
    }

    public function openField(string $id = ''): void
    {
        $this->guardManage();
        $this->errorMessage = '';
        $this->fieldEditingId = $id;
        if ($id) {
            $f = $this->selected->fields()->findOrFail($id);
            $this->fLabel = $f->label;
            $this->fType = $f->type;
            $this->fUnit = (string) $f->unit;
            $this->fRequired = $f->required;
            $this->fValue = $f->value !== null ? rtrim(rtrim(number_format($f->value, 2, '.', ''), '0'), '.') : '';
            $this->fOptions = collect($f->options ?? [])->map(fn ($o) => $o['label'].' = '.$o['value'])->implode("\n");
        } else {
            $this->reset(['fLabel', 'fOptions', 'fUnit', 'fValue', 'fRequired']);
            $this->fType = 'number';
        }
    }

    public function closeField(): void
    {
        $this->reset(['fieldEditingId', 'fLabel', 'fType', 'fOptions', 'fUnit', 'fValue', 'fRequired']);
    }

    public function saveField(): void
    {
        $this->guardManage();
        abort_unless($this->selected !== null, 404);
        $this->validate([
            'fLabel' => ['required', 'string', 'max:80'],
            'fType' => ['required', 'in:'.implode(',', EstimatorField::TYPES)],
        ]);

        $options = null;
        if ($this->fType === 'select') {
            $options = collect(preg_split('/\r?\n/', $this->fOptions))
                ->map(fn ($line) => array_map('trim', explode('=', $line, 2)))
                ->filter(fn ($p) => ($p[0] ?? '') !== '')
                ->map(fn ($p) => ['label' => $p[0], 'value' => (float) ($p[1] ?? 0)])
                ->values()->all();
            if ($options === []) {
                $this->errorMessage = 'A choice field needs at least one option (one per line, e.g. "Small = 50").';

                return;
            }
        }
        if ($this->fType === 'fixed' && ! is_numeric(trim($this->fValue))) {
            $this->errorMessage = 'A set-data field needs a numeric value.';

            return;
        }

        $data = [
            'label' => trim($this->fLabel),
            'type' => $this->fType,
            'options' => $options,
            'value' => $this->fType === 'fixed' ? (float) $this->fValue : null,
            'unit' => trim($this->fUnit) ?: null,
            'required' => $this->fType !== 'fixed' && $this->fRequired,
        ];

        if ($this->fieldEditingId) {
            $this->selected->fields()->findOrFail($this->fieldEditingId)->update($data);
        } else {
            $base = Str::slug($data['label'], '_') ?: 'field';
            $key = $base;
            $n = 1;
            while ($this->selected->fields()->where('key', $key)->exists()) {
                $key = $base.'_'.(++$n);
            }
            $this->selected->fields()->create($data + [
                'site_id' => $this->site->id, 'key' => $key, 'sort' => $this->fields->count(),
            ]);
        }

        $this->dispatch('toast', level: 'success', title: 'Field saved', message: $data['label'].' is part of '.$this->selected->name.'.');
        $this->closeField();
    }

    public function deleteField(string $id): void
    {
        $this->guardManage();
        $field = $this->selected->fields()->findOrFail($id);
        $used = $this->selected->calcs->first(fn ($c) => in_array($field->key, Formula::identifiers($c->formula), true));
        if ($used) {
            $this->errorMessage = "\"{$field->label}\" is used by the \"{$used->name}\" calculation — update that first.";

            return;
        }
        $field->delete();
        $this->dispatch('toast', level: 'success', title: 'Field removed', message: $field->label.' was deleted.');
    }

    // ═══ Calculations (inside the selected estimator) ═══

    public function getCalcsProperty()
    {
        return $this->selected?->calcs()->get() ?? collect();
    }

    public function openCalc(string $id = ''): void
    {
        $this->guardManage();
        $this->errorMessage = '';
        $this->calcEditingId = $id;
        if ($id) {
            $c = $this->selected->calcs()->findOrFail($id);
            $this->cName = $c->name;
            $this->cFormula = $c->formula;
            $this->cFormat = $c->format;
        } else {
            $this->reset(['cName', 'cFormula']);
            $this->cFormat = 'money';
        }
    }

    public function closeCalc(): void
    {
        $this->reset(['calcEditingId', 'cName', 'cFormula', 'cFormat']);
    }

    public function saveCalc(): void
    {
        $this->guardManage();
        abort_unless($this->selected !== null, 404);
        $this->validate([
            'cName' => ['required', 'string', 'max:80'],
            'cFormula' => ['required', 'string', 'max:500'],
            'cFormat' => ['required', 'in:'.implode(',', EstimatorCalc::FORMATS)],
        ]);

        if ($err = Formula::validate($this->cFormula, $this->fields->pluck('key')->all())) {
            $this->errorMessage = $err;

            return;
        }

        $data = ['name' => trim($this->cName), 'formula' => trim(preg_replace('/\s+/', ' ', $this->cFormula)), 'format' => $this->cFormat];
        if ($this->calcEditingId) {
            $this->selected->calcs()->findOrFail($this->calcEditingId)->update($data);
        } else {
            $this->selected->calcs()->create($data + ['site_id' => $this->site->id, 'sort' => $this->calcs->count()]);
        }

        $this->dispatch('toast', level: 'success', title: 'Calculation saved', message: $data['name'].' runs on every '.$this->selected->name.' request.');
        $this->closeCalc();
    }

    public function deleteCalc(string $id): void
    {
        $this->guardManage();
        $this->selected->calcs()->findOrFail($id)->delete();
        $this->dispatch('toast', level: 'success', title: 'Calculation removed', message: 'It no longer runs on new requests.');
    }

    /** Live preview of the selected estimator's calcs (visitor inputs = 1). */
    public function getCalcPreviewProperty(): array
    {
        $vars = [];
        foreach ($this->fields as $f) {
            $vars[$f->key] = $f->type === 'fixed' ? (float) ($f->value ?? 0) : 1.0;
        }
        $currency = strtolower((string) (((array) $this->site->feature('estimator'))['currency'] ?? 'gbp'));

        return $this->calcs->map(fn ($c) => $c->run($vars, $currency))->all();
    }

    // ═══ Leads list ═══

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    public function updateStatus(string $id, string $status): void
    {
        if (in_array($status, self::STATUSES, true)) {
            Estimate::where('site_id', $this->site->id)->whereKey($id)->update(['status' => $status]);
        }
    }

    public function deleteEstimate(string $id): void
    {
        $this->guardManage();
        Estimate::where('site_id', $this->site->id)->whereKey($id)->delete();
    }

    public function getEstimatesProperty()
    {
        return Estimate::where('site_id', $this->site->id)
            ->with('estimator')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('trade', 'like', $term));
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();
    }

    public function getStatusCountsProperty(): array
    {
        $counts = Estimate::where('site_id', $this->site->id)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $counts['all'] = array_sum($counts);

        return $counts;
    }

    public function render()
    {
        return view('livewire.estimates-page');
    }
}
