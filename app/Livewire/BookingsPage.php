<?php

namespace App\Livewire;

use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Models\BookingBlock;
use App\Models\BookingType;
use App\Models\PriceRule;
use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\Booking\SlotAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bookings admin — kind-aware service CRUD (slot | stay | trip), departure
 * management for trip services, and the bookings inbox.
 */
class BookingsPage extends Component
{
    use WithPagination;

    public Site $site;

    /** bookings | services | availability (calendar is a permanent right rail) */
    public string $tab = 'bookings';

    // Calendar state
    public string $calMonth = ''; // Y-m

    public string $calDate = '';  // Y-m-d (selected day)

    // Availability form (site-wide slot settings — bookings feature config)
    public array $availDays = [];

    public string $availOpen = '09:00';

    public string $availClose = '17:00';

    public int $availSlot = 30;

    public int $availLead = 12;

    public int $availHorizon = 30;

    // Per-WEEKDAY hour overrides for the CURRENT scope (site or service):
    // ['fri' => ['open' => '10:00', 'close' => '14:00'], …]
    public array $dayHours = [];

    public string $dhDay = 'fri';

    public string $dhOpen = '';

    public string $dhClose = '';

    public function addDayHour(): void
    {
        if (! in_array($this->dhDay, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)
            || ! preg_match('/^\d{1,2}:\d{2}$/', $this->dhOpen)
            || ! preg_match('/^\d{1,2}:\d{2}$/', $this->dhClose)) {
            $this->addError('dhOpen', 'Pick a weekday and both times.');

            return;
        }
        $this->dayHours[$this->dhDay] = ['open' => $this->dhOpen, 'close' => $this->dhClose];
        $this->reset(['dhOpen', 'dhClose']);
        $this->persistDayHours();
    }

    public function removeDayHour(string $day): void
    {
        unset($this->dayHours[$day]);
        $this->persistDayHours();
    }

    /** Write dayHours to the current scope (site config or service config). */
    private function persistDayHours(): void
    {
        if ($svc = $this->availService()) {
            $config = $svc->config ?? [];
            $config['day_hours'] = $this->dayHours ?: null;
            $svc->update(['config' => array_filter($config, fn ($v) => $v !== null)]);
        } else {
            $existing = (array) $this->site->feature('bookings');
            $existing['day_hours'] = $this->dayHours;
            $this->site->saveFeatureConfig('bookings', $existing);
            $this->site->refresh();
        }
        unset($this->blockDaySlots, $this->planMonths);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Weekday hours updated.');
    }

    // Service form
    public ?string $editingId = null;

    public string $kind = 'slot';

    public string $name = '';

    public int $duration = 30;

    public string $price = '0';

    public string $description = '';

    public bool $requiresPayment = false;

    public int $capacity = 1;

    // stay config
    public int $minNights = 1;

    public int $maxNights = 30;

    public int $maxGuests = 2;

    // slot schedule overrides (blank = site defaults)
    public string $slotDays = '';

    public string $slotOpen = '';

    public string $slotClose = '';

    // ── Creation wizard ──────────────────────────────────────────────────
    public bool $wizOpen = false;

    public int $wizStep = 1;

    public ?string $wizTypeId = null;   // chosen custom type (null = built-in engine)

    /** @var array<int,array{name:string,days:string,open:string,close:string}> */
    public array $wizResources = [];

    public string $wizResName = '';

    public string $wizResDays = '';

    public string $wizResOpen = '';

    public string $wizResClose = '';

    // Deposit (shared by wizard + edit form): none | fixed | pct
    public string $depositMode = 'none';

    public string $depositValue = '';

    // New-type mini-form (field-composed: tick which parameters the type uses)
    public bool $ntOpen = false;

    public string $ntName = '';

    public string $ntIcon = '📅';

    public string $ntEngine = 'slot';

    public string $ntNoun = '';

    /** @var array<int,string> ticked field keys from BookingType::fieldCatalog() */
    public array $ntFields = [];

    // Rules (slot buffers + conditional deposit), stored in services.config
    public int $bufferBefore = 0;

    public int $bufferAfter = 0;

    public int $depositLead = 0; // hours; short-notice bookings pay in full

    public bool $autoConfirm = false; // successful bookings confirm instantly

    // Seasonal price-rule form (service editor)
    public string $prStart = '';

    public string $prEnd = '';

    public string $prPrice = '';

    public string $prLabel = '';

    public string $prResourceId = ''; // '' = whole service

    // Add/edit-service TYPE picker: 'slot'|'stay'|'trip' or 'type:{id}'
    public string $svcType = 'slot';

    // Custom booking-form fields for the service being edited
    /** @var array<int,array{key:string,label:string,type:string,required:bool}> */
    public array $formFields = [];

    public string $ffLabel = '';

    public string $ffType = 'text';

    public string $ffOptions = ''; // comma-separated choices (dropdown type)

    public bool $ffRequired = false;

    public const FORM_FIELD_TYPES = [
        'text' => 'Text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
    ];

    // Site-level resource tile form
    public ?string $srEditingId = null;

    public string $srName = '';

    public int $srCapacity = 1;

    public string $srPrice = ''; // per-resource price override ('' = none)

    // Resource form (staff / rooms / vehicles on the service being edited)
    public string $resName = '';

    public string $resDays = '';

    public string $resOpen = '';

    public string $resClose = '';

    // Departure form (trip services)
    public string $depOrigin = '';

    public string $depDestination = '';

    public string $depDate = '';

    public string $depTime = '';

    public int $depSeats = 10;

    public string $depPrice = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->calMonth = now()->format('Y-m');
        $this->calDate = now()->format('Y-m-d');
        $this->blockDate = now()->addDay()->format('Y-m-d');
        $this->loadAvailability();
    }

    private function loadAvailability(): void
    {
        $c = $this->site->feature('bookings');
        $this->availDays = collect(explode(',', (string) ($c['days'] ?? 'mon,tue,wed,thu,fri')))
            ->map(fn ($d) => strtolower(trim($d)))->filter()->values()->all();
        $this->availOpen = (string) ($c['open_time'] ?? '09:00');
        $this->availClose = (string) ($c['close_time'] ?? '17:00');
        $this->availSlot = (int) ($c['slot_minutes'] ?? 30);
        $this->availLead = (int) ($c['lead_hours'] ?? 12);
        $this->availHorizon = (int) ($c['horizon_days'] ?? 30);
        $this->dayHours = (array) ($c['day_hours'] ?? []);
    }

    /** Header “＋ Create” → open the guided wizard on step 1. */
    public function startCreate(): void
    {
        $this->resetForm();
        $this->reset(['wizTypeId', 'wizResources', 'wizResName', 'wizResDays', 'wizResOpen', 'wizResClose', 'ntOpen', 'ntName', 'ntNoun', 'ntFields']);
        $this->depositMode = 'none';
        $this->depositValue = '';
        $this->wizStep = 1;
        $this->wizOpen = true;
    }

    public function closeWizard(): void
    {
        $this->wizOpen = false;
    }

    #[Computed]
    public function bookingTypes()
    {
        return BookingType::where('site_id', $this->site->id)
            ->orderBy('sort')->orderBy('name')->withCount('services')->get();
    }

    /** Step 1 → choose a built-in engine or a custom type (prefills defaults). */
    public function wizPickType(string $engine, ?string $typeId = null): void
    {
        if (! in_array($engine, Service::KINDS, true)) {
            return;
        }
        $this->kind = $engine;
        $this->wizTypeId = $typeId;

        if ($typeId && ($type = BookingType::where('site_id', $this->site->id)->find($typeId))) {
            $this->kind = $type->engine;
            $this->duration = (int) $type->defaultValue('duration_min', 30);
            $this->price = (string) $type->defaultValue('price', '0');
            $this->capacity = (int) $type->defaultValue('capacity', 1);
            $this->minNights = (int) $type->defaultValue('min_nights', 1);
            $this->maxNights = (int) $type->defaultValue('max_nights', 30);
            $this->maxGuests = (int) $type->defaultValue('max_guests', 2);
            $this->requiresPayment = (bool) $type->defaultValue('requires_payment', false);
            $this->depositMode = (string) $type->defaultValue('deposit_mode', 'none');
            $this->depositValue = (string) $type->defaultValue('deposit_value', '');
        }

        $this->wizStep = 2;
    }

    /** Create a reusable custom type from the mini-form, then select it. */
    public function saveNewType(): void
    {
        $this->validate([
            'ntName' => 'required|string|max:60',
            'ntEngine' => 'required|in:slot,stay,trip',
            'ntIcon' => 'nullable|string|max:8',
            'ntNoun' => 'nullable|string|max:40',
        ]);

        $type = BookingType::create([
            'site_id' => $this->site->id,
            'name' => trim($this->ntName),
            'slug' => trim($this->ntName),
            'icon' => trim($this->ntIcon) ?: '📅',
            'engine' => $this->ntEngine,
            'resource_noun' => trim($this->ntNoun) ?: null,
            'defaults' => [
                'duration_min' => $this->duration,
                'price' => $this->price,
                'deposit_mode' => $this->depositMode,
                'deposit_value' => $this->depositValue,
            ],
            // Ticked parameter keys — the wizard renders ONLY these fields
            // for services of this type. Empty = engine's standard set.
            'fields' => array_values(array_intersect(
                array_keys(BookingType::fieldCatalog($this->ntEngine)),
                $this->ntFields,
            )) ?: null,
            'is_active' => true,
        ]);

        $this->reset(['ntOpen', 'ntName', 'ntNoun', 'ntFields']);
        unset($this->bookingTypes);
        $this->wizPickType($type->engine, $type->id);
        $this->dispatch('toast', level: 'success', title: 'Type created', message: "“{$type->name}” is now reusable for future services.");
    }

    public function toggleType(string $id): void
    {
        $t = BookingType::where('site_id', $this->site->id)->find($id);
        $t?->update(['is_active' => ! $t->is_active]);
        unset($this->bookingTypes);
    }

    public function deleteType(string $id): void
    {
        // Services keep working: kind stays; booking_type_id nulls via FK.
        BookingType::where('site_id', $this->site->id)->whereKey($id)->delete();
        unset($this->bookingTypes, $this->services);
    }

    /**
     * Setting a price auto-ticks "Require payment" when Stripe is connected —
     * owners expect a priced service to charge. They can still untick it
     * (pay-at-desk); clearing the price unticks it again.
     */
    public function updatedPrice($value): void
    {
        if (! $this->site->stripeReady()) {
            return;
        }
        $this->requiresPayment = ((float) $value) > 0;
    }

    /** Blade gate: does the selected type expose this parameter field? */
    public function wizFieldOn(string $key): bool
    {
        if (! $this->wizTypeId) {
            return true; // built-in engines show their standard set
        }

        return $this->bookingTypes->firstWhere('id', $this->wizTypeId)?->fieldEnabled($key) ?? true;
    }

    public function wizBack(): void
    {
        $this->wizStep = max(1, $this->wizStep - 1);
    }

    public function wizNext(): void
    {
        if ($this->wizStep === 2) {
            $this->validate([
                'name' => 'required|string|max:120',
                'price' => 'required|numeric|min:0',
                'depositValue' => $this->depositMode === 'none' ? 'nullable' : 'required|numeric|min:0.01',
            ], [], ['depositValue' => 'deposit']);
        }
        $this->wizStep = min(4, $this->wizStep + 1);
    }

    public function wizAddResource(): void
    {
        if (trim($this->wizResName) === '') {
            return;
        }
        $this->wizResources[] = [
            'name' => trim($this->wizResName),
            'days' => trim($this->wizResDays),
            'open' => trim($this->wizResOpen),
            'close' => trim($this->wizResClose),
        ];
        $this->reset(['wizResName', 'wizResDays', 'wizResOpen', 'wizResClose']);
    }

    public function wizRemoveResource(int $i): void
    {
        unset($this->wizResources[$i]);
        $this->wizResources = array_values($this->wizResources);
    }

    /** Rule config keys (buffers on slot, conditional-deposit lead) — omitted when 0. */
    private function ruleConfig(): array
    {
        return array_filter([
            'buffer_before' => $this->kind === 'slot' ? max(0, $this->bufferBefore) : 0,
            'buffer_after' => $this->kind === 'slot' ? max(0, $this->bufferAfter) : 0,
            'deposit_min_lead_hours' => $this->depositMode !== 'none' ? max(0, $this->depositLead) : 0,
            'auto_confirm' => $this->autoConfirm,
        ]);
    }

    /** The deposit columns derived from the shared mode/value fields. */
    private function depositColumns(): array
    {
        return match ($this->depositMode) {
            'fixed' => ['deposit_cents' => (int) round(((float) $this->depositValue) * 100), 'deposit_pct' => null],
            'pct' => ['deposit_cents' => null, 'deposit_pct' => max(1, min(100, (int) $this->depositValue))],
            default => ['deposit_cents' => null, 'deposit_pct' => null],
        };
    }

    /** Create the service + resources + first departure in one transaction. */
    public function finishWizard(): void
    {
        $this->validate(['name' => 'required|string|max:120', 'price' => 'required|numeric|min:0']);

        $config = match ($this->kind) {
            'stay' => [
                'min_nights' => (int) $this->minNights,
                'max_nights' => max((int) $this->minNights, (int) $this->maxNights),
                'max_guests' => (int) $this->maxGuests,
            ],
            'slot' => array_filter([
                'days' => trim($this->slotDays) ?: null,
                'open_time' => trim($this->slotOpen) ?: null,
                'close_time' => trim($this->slotClose) ?: null,
            ]),
            default => [],
        } + $this->ruleConfig();

        $svc = DB::transaction(function () use ($config) {
            $svc = $this->site->services()->create([
                'name' => $this->name,
                'slug' => $this->name,
                'kind' => $this->kind,
                'booking_type_id' => $this->wizTypeId,
                'duration_min' => $this->duration,
                'price_cents' => (int) round(((float) $this->price) * 100),
                'requires_payment' => $this->requiresPayment || $this->depositMode !== 'none',
                'capacity' => $this->kind === 'trip' ? 1 : max(1, (int) $this->capacity),
                'config' => $config,
                'description' => trim($this->description) ?: null,
                'is_active' => true,
            ] + $this->depositColumns());

            foreach ($this->wizResources as $i => $res) {
                // Attach an EXISTING site resource by exact name, else create.
                $existing = $this->site->resources()->where('name', $res['name'])->first();
                if ($existing) {
                    $svc->resources()->syncWithoutDetaching([$existing->id]);

                    continue;
                }
                $svc->resources()->create([
                    'site_id' => $this->site->id,
                    'name' => $res['name'],
                    'sort' => $i,
                    'is_active' => true,
                    'config' => $svc->kind === 'slot' ? array_filter([
                        'days' => $res['days'] ?: null,
                        'open_time' => $res['open'] ?: null,
                        'close_time' => $res['close'] ?: null,
                    ]) : [],
                ]);
            }

            if ($this->kind === 'trip' && $this->depOrigin && $this->depDestination && $this->depDate && $this->depTime) {
                $svc->departures()->create([
                    'origin' => trim($this->depOrigin),
                    'destination' => trim($this->depDestination),
                    'departs_at' => $this->depDate.' '.$this->depTime,
                    'seats' => max(1, (int) $this->depSeats),
                    'is_active' => true,
                ]);
            }

            return $svc;
        });

        $this->wizOpen = false;
        unset($this->services, $this->bookingTypes);
        $this->tab = 'services';
        $this->editService($svc->id);
        $this->dispatch('toast', level: 'success', title: 'Created', message: "“{$svc->name}” is live — customers can book it now.");
    }

    /** Services-tab type picker: builtin engine or a custom type (prefills defaults). */
    public function pickSvcType(string $value): void
    {
        $this->svcType = $value;
        if (str_starts_with($value, 'type:')) {
            $type = $this->bookingTypes->firstWhere('id', (int) substr($value, 5));
            if (! $type) {
                return;
            }
            $this->kind = $type->engine;
            if (! $this->editingId) { // prefill only when CREATING
                $this->duration = (int) $type->defaultValue('duration_min', 30);
                $this->price = (string) $type->defaultValue('price', '0');
                $this->depositMode = (string) $type->defaultValue('deposit_mode', 'none');
                $this->depositValue = (string) $type->defaultValue('deposit_value', '');
            }
        } else {
            $this->kind = in_array($value, Service::KINDS, true) ? $value : 'slot';
        }
    }

    /** The custom type currently selected in the service form (null = builtin). */
    public function svcTypeModel(): ?BookingType
    {
        return str_starts_with($this->svcType, 'type:')
            ? $this->bookingTypes->firstWhere('id', (int) substr($this->svcType, 5))
            : null;
    }

    /** Field gate for the SERVICE FORM (mirror of wizFieldOn for the wizard). */
    public function svcFieldOn(string $key): bool
    {
        return $this->svcTypeModel()?->fieldEnabled($key) ?? true;
    }

    // ── Custom booking-form fields (per service) ──────────────────────────

    public function addFormField(): void
    {
        $label = trim($this->ffLabel);
        if ($label === '') {
            return;
        }
        $key = Str::slug($label, '_');
        if (collect($this->formFields)->contains(fn ($f) => $f['key'] === $key)) {
            return; // no duplicate keys
        }
        $type = array_key_exists($this->ffType, self::FORM_FIELD_TYPES) ? $this->ffType : 'text';
        $options = collect(explode(',', $this->ffOptions))->map(fn ($o) => trim($o))->filter()->values()->all();
        if ($type === 'select' && ! $options) {
            $this->addError('ffOptions', 'A dropdown needs at least one option (comma-separated).');

            return;
        }
        $this->formFields[] = array_filter([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $this->ffRequired,
            'options' => $type === 'select' ? $options : null,
        ], fn ($v) => $v !== null);
        $this->reset(['ffLabel', 'ffRequired', 'ffOptions']);
        $this->ffType = 'text';
    }

    public function removeFormField(int $i): void
    {
        unset($this->formFields[$i]);
        $this->formFields = array_values($this->formFields);
    }

    /** Stat-tile click → jump to the matching view. */
    public function openTile(string $tile): void
    {
        if ($tile === 'today') {
            $this->pickDate(now()->format('Y-m-d'));
            $this->calMonth = now()->format('Y-m');
            unset($this->calendarDays);
        }
        $this->setTab('bookings');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['calendar', 'bookings', 'services', 'availability'], true)) {
            $this->tab = $tab;
        }
    }

    // ── Calendar ──────────────────────────────────────────────────────────

    public function calShift(int $months): void
    {
        $this->calMonth = Carbon::parse($this->calMonth.'-01')->addMonths($months)->format('Y-m');
        unset($this->calendarDays);
    }

    public function pickDate(string $date): void
    {
        $this->calDate = $date;
        unset($this->dayBookings);
    }

    /**
     * Month grid: 42 cells with per-day ACTIVE booking counts.
     *
     * @return array<int,array{date:string,day:int,inMonth:bool,count:int,isToday:bool}>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $first = Carbon::parse($this->calMonth.'-01');
        $start = $first->copy()->subDays($first->dayOfWeek); // grid starts Sunday
        $end = $start->copy()->addDays(41);

        $counts = $this->site->bookings()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['starts_at', 'ends_at'])
            // A stay spans nights — it appears on every day of its range.
            ->flatMap(function ($b) {
                $from = $b->starts_at->copy()->startOfDay();
                $to = $b->ends_at && $b->ends_at->gt($b->starts_at) ? $b->ends_at->copy()->startOfDay() : $from;
                $days = [];
                for ($d = $from->copy(); $d->lte($to) && count($days) < 60; $d->addDay()) {
                    $days[] = $d->format('Y-m-d');
                }

                return $days;
            })
            ->countBy();

        $today = now()->format('Y-m-d');
        $out = [];
        for ($d = $start->copy(), $i = 0; $i < 42; $i++, $d->addDay()) {
            $key = $d->format('Y-m-d');
            $out[] = [
                'date' => $key,
                'day' => $d->day,
                'inMonth' => $d->format('Y-m') === $this->calMonth,
                'count' => (int) ($counts[$key] ?? 0),
                'isToday' => $key === $today,
            ];
        }

        return $out;
    }

    /** Bookings touching the selected date (stays include their whole range). */
    #[Computed]
    public function dayBookings()
    {
        $day = Carbon::parse($this->calDate);

        return $this->site->bookings()->with(['service', 'departure'])
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $day->copy()->endOfDay())
            ->where('ends_at', '>=', $day->copy()->startOfDay())
            ->orderBy('starts_at')
            ->get();
    }

    // ── Availability (site-wide slot settings) ───────────────────────────

    public function toggleDay(string $day): void
    {
        $this->availDays = in_array($day, $this->availDays, true)
            ? array_values(array_diff($this->availDays, [$day]))
            : array_merge($this->availDays, [$day]);
    }

    public function saveAvailability(): void
    {
        $this->validate([
            'availOpen' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'availClose' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'availSlot' => 'required|integer|min:5|max:240',
            'availLead' => 'required|integer|min:0|max:720',
            'availHorizon' => 'required|integer|min:1|max:365',
        ]);
        if (empty($this->availDays)) {
            $this->dispatch('toast', level: 'error', title: 'No days selected', message: 'Pick at least one bookable day.');

            return;
        }

        $existing = (array) $this->site->feature('bookings');
        $this->site->saveFeatureConfig('bookings', array_merge($existing, [
            'days' => implode(',', $this->availDays),
            'open_time' => $this->availOpen,
            'close_time' => $this->availClose,
            'slot_minutes' => $this->availSlot,
            'lead_hours' => $this->availLead,
            'horizon_days' => $this->availHorizon,
        ]));
        $this->site->refresh();
        unset($this->planMonths);

        $this->dispatch('toast', level: 'success', title: 'Availability saved', message: 'New slots apply to all future bookings.');
    }

    // ── Day & slot exceptions (its own tile in Availability) ─────────────

    public string $blockDate = '';

    /** '' = whole site; a service id scopes the availability tab to it. */
    public string $availServiceId = '';

    // Per-service schedule/behavior mini-form (availability tab)
    public string $asDays = '';

    public string $asOpen = '';

    public string $asClose = '';

    public bool $asAutoConfirm = false;

    /** The slot service the availability tab is scoped to (null = site). */
    public function availService(): ?Service
    {
        return $this->availServiceId === ''
            ? null
            : $this->site->services()->find((int) $this->availServiceId);
    }

    public function pickAvailService(string $id): void
    {
        $this->availServiceId = $id;
        if ($svc = $this->availService()) {
            $this->asDays = (string) $svc->configValue('days', '');
            $this->asOpen = (string) $svc->configValue('open_time', '');
            $this->asClose = (string) $svc->configValue('close_time', '');
            $this->asAutoConfirm = (bool) $svc->configValue('auto_confirm', false);
            $this->dayHours = (array) $svc->configValue('day_hours', []);
        } else {
            $this->dayHours = (array) (((array) $this->site->feature('bookings'))['day_hours'] ?? []);
        }
        unset($this->blockDaySlots, $this->blockDayOff, $this->blockedDates, $this->planMonths);
    }

    /** Save the selected service's schedule override + confirmation mode. */
    public function saveServiceAvailability(): void
    {
        $svc = $this->availService();
        if (! $svc) {
            return;
        }
        $config = array_merge($svc->config ?? [], [
            'days' => trim($this->asDays) ?: null,
            'open_time' => trim($this->asOpen) ?: null,
            'close_time' => trim($this->asClose) ?: null,
            'auto_confirm' => $this->asAutoConfirm,
        ]);
        $svc->update(['config' => array_filter($config, fn ($v) => $v !== null)]);
        unset($this->planMonths, $this->blockDaySlots, $this->services);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: "“{$svc->name}” availability updated.");
    }

    /** Planner slider: 0 = current month, up to 2 months ahead. */
    public int $planShift = 0;

    public function planShiftBy(int $d): void
    {
        $this->planShift = max(0, min(2, $this->planShift + $d));
        unset($this->planMonths);
    }

    // Custom hours for the selected date ('' = follow the schedule)
    public string $bdOpen = '';

    public string $bdClose = '';

    /** Select a day in the planner (slides to its month if needed). */
    public function pickBlockDate(string $date): void
    {
        $this->blockDate = $date;
        $diff = (int) now()->startOfMonth()->diffInMonths(Carbon::parse($date)->startOfMonth());
        $this->planShift = max(0, min(2, $diff));
        $hours = BookingBlock::forDay($this->site, $date, $this->availService())['hours'];
        $this->bdOpen = $hours['open'] ?? '';
        $this->bdClose = $hours['close'] ?? '';
        unset($this->blockDaySlots, $this->blockDayOff, $this->planMonths);
    }

    /** Save custom opening hours for the selected date (scoped like blocks). */
    public function saveDayHours(): void
    {
        if ($this->blockDate === '') {
            return;
        }
        $this->validate([
            'bdOpen' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'bdClose' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
        ], [], ['bdOpen' => 'opens', 'bdClose' => 'closes']);

        $sid = $this->availServiceId === '' ? null : (int) $this->availServiceId;
        $row = BookingBlock::where('site_id', $this->site->id)
            ->where('service_id', $sid)->whereDate('date', $this->blockDate)
            ->whereNull('start_time')->whereNotNull('open_time')->first();
        $row
            ? $row->update(['open_time' => $this->bdOpen, 'close_time' => $this->bdClose])
            : BookingBlock::create([
                'site_id' => $this->site->id, 'service_id' => $sid, 'date' => $this->blockDate,
                'start_time' => null, 'open_time' => $this->bdOpen, 'close_time' => $this->bdClose,
            ]);

        unset($this->blockDaySlots, $this->blockedDates, $this->planMonths);
        $this->dispatch('toast', level: 'success', title: 'Hours set', message: Carbon::parse($this->blockDate)->format('D, M j')." opens {$this->bdOpen}–{$this->bdClose}.");
    }

    /** Remove the custom hours — the date returns to the normal schedule. */
    public function clearDayHours(): void
    {
        $sid = $this->availServiceId === '' ? null : (int) $this->availServiceId;
        BookingBlock::where('site_id', $this->site->id)
            ->where('service_id', $sid)->whereDate('date', $this->blockDate)
            ->whereNull('start_time')->whereNotNull('open_time')->delete();
        $this->bdOpen = '';
        $this->bdClose = '';
        unset($this->blockDaySlots, $this->blockedDates, $this->planMonths);
    }

    /**
     * 3-month availability planner: months → weeks → day cells so the owner
     * can open/close days up to three months ahead at a glance.
     *
     * Cell: [date, day, open (weekday in schedule), blocked (whole day),
     *        slotBlocks (partial), past, today]
     */
    #[Computed]
    public function planMonths(): array
    {
        // Service scope: its day override (if any) wins; its blocks stack
        // on the site-wide ones.
        $svc = $this->availService();
        $days = $svc && trim((string) $svc->configValue('days'))
            ? collect(explode(',', (string) $svc->configValue('days')))->map(fn ($d) => strtolower(trim($d)))->filter()->all()
            : $this->availDays;
        $schedDays = array_flip($days); // mon..sun keys
        $blocks = BookingBlock::where('site_id', $this->site->id)
            ->where(fn ($q) => $q->whereNull('service_id')
                ->when($svc, fn ($qq) => $qq->orWhere('service_id', $svc->id)))
            ->whereDate('date', '>=', now()->startOfMonth())
            ->whereDate('date', '<=', now()->addMonths(3)->endOfMonth())
            ->get()
            ->groupBy(fn ($b) => $b->date->format('Y-m-d'));

        $months = [];
        foreach ([$this->planShift] as $m) { // slider: one month at a time
            $first = now()->startOfMonth()->addMonths($m);
            $cells = [];
            // leading blanks (week starts Monday)
            for ($i = 0, $lead = $first->dayOfWeekIso - 1; $i < $lead; $i++) {
                $cells[] = null;
            }
            for ($d = $first->copy(); $d->month === $first->month; $d->addDay()) {
                $key = $d->format('Y-m-d');
                $dayBlocks = $blocks->get($key, collect());
                $cells[] = [
                    'date' => $key,
                    'day' => $d->day,
                    'open' => isset($schedDays[strtolower($d->format('D'))]),
                    'blocked' => $dayBlocks->contains(fn ($b) => $b->start_time === null && $b->open_time === null),
                    'hours' => $dayBlocks->contains(fn ($b) => $b->start_time === null && $b->open_time !== null),
                    'slotBlocks' => $dayBlocks->contains(fn ($b) => $b->start_time !== null),
                    'past' => $d->isPast() && ! $d->isToday(),
                    'today' => $d->isToday(),
                ];
            }

            $months[] = ['label' => $first->format('F Y'), 'cells' => $cells];
        }

        return $months;
    }

    /**
     * The full schedule grid for the exception date, block state included —
     * built from the site schedule (bookings and lead time ignored: the admin
     * is editing the TEMPLATE of the day, not live availability).
     *
     * @return array<int,array{time:string,label:string,blocked:bool}>
     */
    #[Computed]
    public function blockDaySlots(): array
    {
        if ($this->blockDate === '') {
            return [];
        }
        // SAME settings cascade as the public engine (resource > service > site),
        // so the grid shown here is exactly the slot template customers book from
        // — a service with its own hours (e.g. evenings only) shows those hours.
        $s = app(SlotAvailability::class)->settings($this->site, $this->availService());
        $blocks = BookingBlock::forDay($this->site, $this->blockDate, $this->availService());
        // Hour precedence mirrors the engine: date exception > weekday > schedule.
        $wd = strtolower(Carbon::parse($this->blockDate)->format('D'));
        $weekday = $s['day_hours'][$wd] ?? null;
        $open = $blocks['hours']['open'] ?? $weekday['open'] ?? $s['open'];
        $close = $blocks['hours']['close'] ?? $weekday['close'] ?? $s['close'];
        [$oh, $om] = array_pad(array_map('intval', explode(':', $open)), 2, 0);
        [$ch, $cm] = array_pad(array_map('intval', explode(':', $close)), 2, 0);
        $step = $s['slot'];
        $cursor = Carbon::parse($this->blockDate)->setTime($oh, $om);
        $close = Carbon::parse($this->blockDate)->setTime($ch, $cm);

        $out = [];
        while ($cursor->lt($close)) {
            $t = $cursor->format('H:i');
            $out[] = ['time' => $t, 'label' => $cursor->format('g:i A'), 'blocked' => isset($blocks['times'][$t])];
            $cursor->addMinutes($step);
        }

        return $out;
    }

    #[Computed]
    public function blockDayOff(): bool
    {
        return $this->blockDate !== ''
            && BookingBlock::forDay($this->site, $this->blockDate, $this->availService())['dayBlocked'];
    }

    /** Dates that carry any exception — listed so nothing is forgotten. */
    #[Computed]
    public function blockedDates()
    {
        return BookingBlock::where('site_id', $this->site->id)
            ->where(fn ($q) => $q->whereNull('service_id')
                ->when($this->availService(), fn ($qq, $svc) => $qq->orWhere('service_id', $svc->id)))
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($b) => $b->date->format('Y-m-d'))
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'dayOff' => $rows->contains(fn ($r) => $r->start_time === null && $r->open_time === null),
                'hours' => ($h = $rows->first(fn ($r) => $r->start_time === null && $r->open_time !== null))
                    ? substr($h->open_time, 0, 5).'–'.substr($h->close_time, 0, 5) : null,
                'slots' => $rows->whereNotNull('start_time')->count(),
            ])->values();
    }

    public function toggleSlotBlock(string $time): void
    {
        if ($this->blockDate === '' || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return;
        }
        $sid = $this->availServiceId === '' ? null : (int) $this->availServiceId;
        $existing = BookingBlock::where('site_id', $this->site->id)
            ->where('service_id', $sid)
            ->whereDate('date', $this->blockDate)->where('start_time', $time.':00')->first();
        $existing
            ? $existing->delete()
            : BookingBlock::create(['site_id' => $this->site->id, 'service_id' => $sid, 'date' => $this->blockDate, 'start_time' => $time]);
        unset($this->blockDaySlots, $this->blockedDates, $this->planMonths);
    }

    public function toggleDayBlock(): void
    {
        if ($this->blockDate === '') {
            return;
        }
        $sid = $this->availServiceId === '' ? null : (int) $this->availServiceId;
        $existing = BookingBlock::where('site_id', $this->site->id)
            ->where('service_id', $sid)
            ->whereDate('date', $this->blockDate)->whereNull('start_time')->whereNull('open_time')->first();
        $existing
            ? $existing->delete()
            : BookingBlock::create(['site_id' => $this->site->id, 'service_id' => $sid, 'date' => $this->blockDate, 'start_time' => null]);
        unset($this->blockDayOff, $this->blockedDates, $this->planMonths);
    }

    /** Remove every exception on a date (from the exceptions list). */
    public function clearBlocks(string $date): void
    {
        $sid = $this->availServiceId === '' ? null : (int) $this->availServiceId;
        BookingBlock::where('site_id', $this->site->id)
            ->where('service_id', $sid)->whereDate('date', $date)->delete();
        unset($this->blockDaySlots, $this->blockDayOff, $this->blockedDates, $this->planMonths);
    }

    /** Headline tiles: today · next 7 days · pending · confirmed this month. */
    #[Computed]
    public function tiles(): array
    {
        $active = fn () => $this->site->bookings()->where('status', '!=', 'cancelled');

        return [
            // Bookings RECEIVED today (any status — owners think in "how many
            // came in today", not "what's on today's calendar" — the calendar
            // rail answers the latter).
            'today' => $this->site->bookings()->whereDate('created_at', now()->toDateString())->count(),
            'upcoming' => $active()->whereBetween('starts_at', [now(), now()->addDays(7)])->count(),
            'pending' => $this->site->bookings()->where('status', 'pending')->count(),
            // Confirmed bookings still ahead (not bound to the calendar month —
            // "0" on the 31st with a full first-of-month reads as broken).
            'month' => $this->site->bookings()->where('status', 'confirmed')
                ->where('ends_at', '>=', now())->count(),
        ];
    }

    #[Computed]
    public function services()
    {
        return $this->site->services()->withCount('departures')->orderBy('sort')->orderBy('name')->get();
    }

    #[Computed]
    public function bookings()
    {
        return $this->site->bookings()->with(['service', 'departure'])->orderByDesc('starts_at')->paginate(10);
    }

    #[Computed]
    public function departures()
    {
        if (! $this->editingId) {
            return collect();
        }

        return $this->site->services()->find($this->editingId)
            ?->departures()->orderBy('departs_at')->get() ?? collect();
    }

    public function saveService(): void
    {
        $data = $this->validate([
            'kind' => 'required|in:slot,stay,trip',
            'name' => 'required|string|max:120',
            'duration' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'capacity' => 'required|integer|min:1|max:1000',
            'minNights' => 'required|integer|min:1|max:365',
            'maxNights' => 'required|integer|min:1|max:365',
            'maxGuests' => 'required|integer|min:1|max:100',
        ]);

        $config = match ($data['kind']) {
            'stay' => [
                'min_nights' => (int) $data['minNights'],
                'max_nights' => max((int) $data['minNights'], (int) $data['maxNights']),
                'max_guests' => (int) $data['maxGuests'],
            ],
            'slot' => array_filter([
                'days' => trim($this->slotDays) ?: null,
                'open_time' => trim($this->slotOpen) ?: null,
                'close_time' => trim($this->slotClose) ?: null,
            ]),
            default => [],
        } + $this->ruleConfig();

        if ($this->formFields) {
            $config['form_fields'] = $this->formFields;
        }

        $attrs = [
            'name' => $data['name'],
            'slug' => $data['name'],
            'kind' => $data['kind'],
            'booking_type_id' => $this->svcTypeModel()?->id,
            'duration_min' => $data['duration'],
            'price_cents' => (int) round(((float) $data['price']) * 100),
            'requires_payment' => $this->requiresPayment || $this->depositMode !== 'none',
            'capacity' => $data['kind'] === 'trip' ? 1 : (int) $data['capacity'],
            'config' => $config,
            'description' => $data['description'] ?: null,
        ] + $this->depositColumns();

        if ($this->editingId) {
            $this->site->services()->whereKey($this->editingId)->first()?->update($attrs);
        } else {
            $svc = $this->site->services()->create($attrs + ['is_active' => true]);
            // Trip services go straight into editing so departures can be added.
            if ($svc->kind === 'trip') {
                $this->editService($svc->id);
                unset($this->services);
                $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Service saved — now add departures below.');

                return;
            }
        }

        if (! $this->editingId) {
            $this->resetForm();
        }
        unset($this->services);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Service saved.');
    }

    public function editService(string $id): void
    {
        $s = $this->site->services()->findOrFail($id);
        $this->editingId = $s->id;
        $this->kind = $s->kind;
        $this->name = $s->name;
        $this->duration = $s->duration_min;
        $this->price = number_format($s->price_cents / 100, 2, '.', '');
        $this->description = (string) $s->description;
        $this->requiresPayment = (bool) $s->requires_payment;
        $this->capacity = max(1, (int) $s->capacity);
        $this->minNights = (int) $s->configValue('min_nights', 1);
        $this->maxNights = (int) $s->configValue('max_nights', 30);
        $this->maxGuests = (int) $s->configValue('max_guests', 2);
        $this->slotDays = (string) $s->configValue('days', '');
        $this->slotOpen = (string) $s->configValue('open_time', '');
        $this->slotClose = (string) $s->configValue('close_time', '');
        $this->bufferBefore = (int) $s->configValue('buffer_before', 0);
        $this->bufferAfter = (int) $s->configValue('buffer_after', 0);
        $this->depositLead = (int) $s->configValue('deposit_min_lead_hours', 0);
        $this->autoConfirm = (bool) $s->configValue('auto_confirm', false);
        $this->svcType = $s->booking_type_id ? 'type:'.$s->booking_type_id : $s->kind;
        $this->formFields = $s->formFields();
        [$this->depositMode, $this->depositValue] = match (true) {
            ($s->deposit_cents ?? 0) > 0 => ['fixed', number_format($s->deposit_cents / 100, 2, '.', '')],
            ($s->deposit_pct ?? 0) > 0 => ['pct', (string) $s->deposit_pct],
            default => ['none', ''],
        };
        unset($this->departures);
    }

    public function deleteService(string $id): void
    {
        $this->site->services()->whereKey($id)->delete();
        unset($this->services);
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function toggleService(string $id): void
    {
        $s = $this->site->services()->findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        unset($this->services);
    }

    // ── Resources (staff / rooms / vehicles) ──────────────────────────────

    #[Computed]
    public function serviceResources()
    {
        if (! $this->editingId) {
            return collect();
        }

        return $this->site->services()->find($this->editingId)
            ?->resources()->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', '!=', 'cancelled')->where('starts_at', '>=', now())])
            ->orderBy('sort')->orderBy('name')->get() ?? collect();
    }

    public function saveResource(): void
    {
        $svc = $this->editingId ? $this->site->services()->find($this->editingId) : null;
        if (! $svc) {
            return;
        }
        $this->validate(['resName' => 'required|string|max:120']);

        $config = $svc->kind === 'slot' ? array_filter([
            'days' => trim($this->resDays) ?: null,
            'open_time' => trim($this->resOpen) ?: null,
            'close_time' => trim($this->resClose) ?: null,
        ]) : [];

        $svc->resources()->create(['site_id' => $this->site->id, 'name' => trim($this->resName), 'config' => $config, 'is_active' => true]);
        $this->reset(['resName', 'resDays', 'resOpen', 'resClose']);
        unset($this->serviceResources, $this->services);
        $this->dispatch('toast', level: 'success', title: 'Added', message: ServiceResource::noun($svc->kind).' added.');
    }

    public function toggleResource(string $id): void
    {
        $svc = $this->editingId ? $this->site->services()->find($this->editingId) : null;
        $r = $svc?->resources()->whereKey($id)->first();
        $r?->update(['is_active' => ! $r->is_active]);
        unset($this->serviceResources);
    }

    public function deleteResource(string $id): void
    {
        // Resources are SITE-LEVEL and shared: removing here only DETACHES
        // it from this service — other services keep it. True deletion lives
        // in the site Resources tile.
        $svc = $this->editingId ? $this->site->services()->find($this->editingId) : null;
        $svc?->resources()->detach($id);
        unset($this->serviceResources, $this->services);
    }

    // ── Seasonal price rules (service editor) ─────────────────────────────

    #[Computed]
    public function priceRules()
    {
        if (! $this->editingId) {
            return collect();
        }

        return PriceRule::where('site_id', $this->site->id)
            ->where(function ($q) {
                $q->where('service_id', $this->editingId)
                    ->orWhereIn('resource_id', $this->site->services()->find($this->editingId)
                        ?->resources()->pluck('service_resources.id') ?? []);
            })
            ->orderBy('starts_on')->with('resource')->get();
    }

    public function addPriceRule(): void
    {
        if (! $this->editingId) {
            return;
        }
        $this->validate([
            'prStart' => 'required|date',
            'prEnd' => 'required|date|after_or_equal:prStart',
            'prPrice' => 'required|numeric|min:0',
            'prLabel' => 'nullable|string|max:60',
        ], [], ['prStart' => 'start date', 'prEnd' => 'end date', 'prPrice' => 'price']);

        PriceRule::create([
            'site_id' => $this->site->id,
            // A rule targets EITHER a specific resource or the whole service.
            'service_id' => $this->prResourceId === '' ? $this->editingId : null,
            'resource_id' => $this->prResourceId === '' ? null : (int) $this->prResourceId,
            'starts_on' => $this->prStart,
            'ends_on' => $this->prEnd,
            'price_cents' => (int) round(((float) $this->prPrice) * 100),
            'label' => trim($this->prLabel) ?: null,
        ]);

        $this->reset(['prStart', 'prEnd', 'prPrice', 'prLabel', 'prResourceId']);
        unset($this->priceRules);
        $this->dispatch('toast', level: 'success', title: 'Rule added', message: 'Seasonal price rule saved.');
    }

    public function deletePriceRule(string $id): void
    {
        PriceRule::where('site_id', $this->site->id)->whereKey($id)->delete();
        unset($this->priceRules);
    }

    // ── Site-level resources (shared across services) ─────────────────────

    #[Computed]
    public function siteResources()
    {
        return $this->site->resources()->with('services:id,name')
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', '!=', 'cancelled')->where('starts_at', '>=', now())])
            ->orderBy('sort')->orderBy('name')->get();
    }

    public function saveSiteResource(): void
    {
        $this->validate([
            'srName' => 'required|string|max:120',
            'srCapacity' => 'required|integer|min:1|max:1000',
            'srPrice' => 'nullable|numeric|min:0',
        ], [], ['srName' => 'name', 'srCapacity' => 'capacity']);

        $attrs = [
            'name' => trim($this->srName),
            'capacity' => (int) $this->srCapacity,
            'price_cents' => $this->srPrice === '' ? null : (int) round(((float) $this->srPrice) * 100),
        ];

        if ($this->srEditingId) {
            $this->site->resources()->whereKey($this->srEditingId)->first()?->update($attrs);
        } else {
            $this->site->resources()->create($attrs + ['is_active' => true]);
        }

        $this->reset(['srEditingId', 'srName', 'srCapacity', 'srPrice']);
        $this->srCapacity = 1;
        unset($this->siteResources, $this->serviceResources);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Resource saved.');
    }

    public function editSiteResource(string $id): void
    {
        $r = $this->site->resources()->findOrFail($id);
        $this->srEditingId = $r->id;
        $this->srName = $r->name;
        $this->srCapacity = max(1, (int) $r->capacity);
        $this->srPrice = $r->price_cents === null ? '' : number_format($r->price_cents / 100, 2, '.', '');
    }

    public function toggleSiteResource(string $id): void
    {
        $r = $this->site->resources()->whereKey($id)->first();
        $r?->update(['is_active' => ! $r->is_active]);
        unset($this->siteResources, $this->serviceResources);
    }

    /** TRUE deletion — detaches from every service and removes the resource. */
    public function deleteSiteResource(string $id): void
    {
        $r = $this->site->resources()->whereKey($id)->first();
        $r?->services()->detach();
        $r?->delete();
        unset($this->siteResources, $this->serviceResources, $this->services);
    }

    /** Assign / unassign a shared resource to a service (checkbox toggle). */
    public function toggleResourceService(string $resourceId, string $serviceId): void
    {
        $r = $this->site->resources()->whereKey($resourceId)->first();
        $svc = $this->site->services()->whereKey($serviceId)->first();
        if (! $r || ! $svc) {
            return;
        }
        $r->services()->toggle($svc->id);
        unset($this->siteResources, $this->serviceResources, $this->services);
    }

    // ── Departures (trip services) ────────────────────────────────────────

    public function saveDeparture(): void
    {
        $svc = $this->editingId ? $this->site->services()->find($this->editingId) : null;
        if (! $svc || $svc->kind !== 'trip') {
            return;
        }

        $this->validate([
            'depOrigin' => 'required|string|max:120',
            'depDestination' => 'required|string|max:120',
            'depDate' => 'required|date',
            'depTime' => 'required|string|max:5',
            'depSeats' => 'required|integer|min:1|max:1000',
            'depPrice' => 'nullable|numeric|min:0',
        ]);

        $svc->departures()->create([
            'origin' => trim($this->depOrigin),
            'destination' => trim($this->depDestination),
            'departs_at' => $this->depDate.' '.$this->depTime,
            'seats' => $this->depSeats,
            'price_cents' => $this->depPrice === '' ? null : (int) round(((float) $this->depPrice) * 100),
            'is_active' => true,
        ]);

        $this->reset(['depDate', 'depTime']);
        unset($this->departures);
        $this->dispatch('toast', level: 'success', title: 'Departure added', message: $this->depOrigin.' → '.$this->depDestination);
    }

    public function deleteDeparture(string $id): void
    {
        $svc = $this->editingId ? $this->site->services()->find($this->editingId) : null;
        $svc?->departures()->whereKey($id)->delete();
        unset($this->departures);
    }

    // ── Booking detail (modal) ────────────────────────────────────────────

    public ?string $viewingId = null;

    public function viewBooking(string $id): void
    {
        $this->viewingId = $this->site->bookings()->whereKey($id)->exists() ? $id : null;
    }

    public function closeBooking(): void
    {
        $this->viewingId = null;
    }

    #[Computed]
    public function viewedBooking()
    {
        return $this->viewingId
            ? $this->site->bookings()->with(['service.bookingType', 'departure', 'resource'])->find($this->viewingId)
            : null;
    }

    /** Record the outstanding balance as collected (cash/at desk). */
    public function markFullyPaid(): void
    {
        $b = $this->viewedBooking;
        if ($b && $b->balanceCents() > 0) {
            $b->update(['paid_cents' => $b->total_cents]);
            unset($this->viewedBooking, $this->bookings, $this->dayBookings);
            $this->dispatch('toast', level: 'success', title: 'Balance settled', message: "{$b->reference} is fully paid.");
        }
    }

    // ── Bookings inbox ────────────────────────────────────────────────────

    public function setStatus(string $bookingId, string $status): void
    {
        if (! in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
            return;
        }
        $booking = $this->site->bookings()->with('service')->whereKey($bookingId)->first();
        if (! $booking) {
            return;
        }
        $was = $booking->status;
        $booking->update(['status' => $status]);

        if (in_array($status, ['confirmed', 'cancelled'], true) && $was !== $status) {
            try {
                ActivityLogger::bookingEvent($booking, $status);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($status === 'confirmed' && $was !== 'confirmed') {
            try {
                Mail::to($booking->customer_email)->send(new BookingConfirmed($booking, $this->site));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($status === 'cancelled' && $was !== 'cancelled') {
            try {
                Mail::to($booking->customer_email)->send(new BookingCancelled($booking, $this->site));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        unset($this->bookings, $this->dayBookings, $this->calendarDays, $this->tiles, $this->viewedBooking);
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'kind', 'name', 'duration', 'price', 'description',
            'requiresPayment', 'capacity', 'minNights', 'maxNights', 'maxGuests',
            'slotDays', 'slotOpen', 'slotClose',
            'bufferBefore', 'bufferAfter', 'depositLead', 'autoConfirm',
            'svcType', 'formFields', 'ffLabel', 'ffType', 'ffOptions', 'ffRequired',
            'prStart', 'prEnd', 'prPrice', 'prLabel', 'prResourceId',
            'depOrigin', 'depDestination', 'depDate', 'depTime', 'depSeats', 'depPrice',
        ]);
        $this->duration = 30;
        $this->price = '0';
        $this->capacity = 1;
        $this->depSeats = 10;
        $this->depositMode = 'none';
        $this->depositValue = '';
    }

    public function render()
    {
        return view('livewire.bookings-page');
    }
}
