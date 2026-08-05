<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EstimateQuoteMail;
use App\Mail\FormSubmissionNotification;
use App\Models\Alert;
use App\Models\Contact;
use App\Models\Estimate;
use App\Models\Estimator;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\EstimatorEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Estimator module API — instant cost + completion-time quotes for trade
 * services, computed SERVER-side from config/estimator.php (± site scaling).
 *
 *   GET  /api/sites/{site}/estimator/config   → trades + inputs (no rates)
 *   POST /api/sites/{site}/estimator          → { trade, inputs{} } → quote
 *   POST /api/sites/{site}/estimator/request  → save lead + email both parties
 */
class EstimatorController extends Controller
{
    public function __construct(private EstimatorEngine $engine) {}

    private function site(string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('estimator'), 404);

        return $site;
    }

    public function config(string $siteName): JsonResponse
    {
        $site = $this->site($siteName);

        // Admin-built estimators — each with its public fields (fixed "set
        // data" fields stay private: they join the calculation server-side,
        // never the public form).
        $estimators = $site->estimators()->with(['fields', 'calcs'])->get()
            ->map(fn ($e) => [
                'key' => $e->slug,
                'name' => $e->name,
                'fields' => $e->fields->where('type', '!=', 'fixed')->values()
                    ->map(fn ($f) => [
                        'key' => $f->key, 'label' => $f->label, 'type' => $f->type,
                        'unit' => $f->unit, 'required' => $f->required,
                        'options' => array_column($f->options ?? [], 'label'),
                    ])->all(),
                'has_calculations' => $e->calcs->isNotEmpty(),
            ])->all();

        return response()->json($this->engine->config($site) + ['estimators' => $estimators]);
    }

    /** Resolve a requested estimator by slug (or the site's only one). */
    private function resolveEstimator(Site $site, ?string $key): ?Estimator
    {
        $estimators = $site->estimators;
        if ($key) {
            return $estimators->firstWhere('slug', $key);
        }

        return $estimators->count() === 1 ? $estimators->first() : null;
    }

    /**
     * Run one estimator's calculations for a set of visitor field values.
     * Fixed fields contribute their set data automatically.
     *
     * @return array{vars: array<string,float>, results: array<int,array>, currency: string}
     */
    private function customResults(Site $site, ?Estimator $estimator, array $fieldValues): array
    {
        $f = (array) $site->feature('estimator');
        $currency = strtolower((string) ($f['currency'] ?? 'gbp'));

        if (! $estimator) {
            return ['vars' => [], 'results' => [], 'currency' => $currency];
        }

        $vars = [];
        foreach ($estimator->fields as $field) {
            $vars[$field->key] = $field->numericValue($fieldValues[$field->key] ?? null);
        }

        $results = $estimator->calcs->map(fn ($c) => $c->run($vars, $currency))->values()->all();

        return ['vars' => $vars, 'results' => $results, 'currency' => $currency];
    }

    /** Instant quote — nothing stored; the widget calls this as inputs change. */
    public function estimate(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'trade' => ['nullable', 'string', 'max:40'],
            'estimator' => ['nullable', 'string', 'max:80'], // estimator slug
            'inputs' => ['nullable', 'array'],
            'fields' => ['nullable', 'array'], // estimator field values
        ]);

        $quote = ! empty($data['trade'])
            ? $this->engine->estimate($site, $data['trade'], (array) ($data['inputs'] ?? []))
            : [];
        if (! empty($data['trade']) && ! $quote) {
            return response()->json(['message' => 'Unknown trade.'], 404);
        }

        $estimator = $this->resolveEstimator($site, $data['estimator'] ?? null);
        $custom = $this->customResults($site, $estimator, (array) ($data['fields'] ?? []));
        if (! $quote && ! $custom['results']) {
            return response()->json(['message' => 'Nothing to calculate.'], 422);
        }

        return response()->json(($quote ?: []) + ['results' => $custom['results']]);
    }

    /** Save the estimate as a lead and email BOTH the owner and the visitor. */
    public function store(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $data = $request->validate([
            'trade' => ['nullable', 'string', 'max:40'],
            'estimator' => ['nullable', 'string', 'max:80'], // estimator slug
            'inputs' => ['nullable', 'array'],
            'fields' => ['nullable', 'array'], // estimator field values
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Trade engine quote (config catalog) and/or one of the admin's estimators.
        $quote = ! empty($data['trade'])
            ? $this->engine->estimate($site, $data['trade'], (array) ($data['inputs'] ?? []))
            : null;
        if (! empty($data['trade']) && ! $quote) {
            return response()->json(['message' => 'Unknown trade.'], 404);
        }

        $estimator = $this->resolveEstimator($site, $data['estimator'] ?? null);

        // Required estimator fields.
        $fieldValues = (array) ($data['fields'] ?? []);
        foreach ($estimator?->fields->where('type', '!=', 'fixed')->where('required', true) ?? [] as $rf) {
            if (! array_key_exists($rf->key, $fieldValues) || $fieldValues[$rf->key] === '' || $fieldValues[$rf->key] === null) {
                return response()->json(['message' => "Missing required field: {$rf->label}."], 422);
            }
        }

        $custom = $this->customResults($site, $estimator, $fieldValues);
        if (! $quote && ! $custom['results']) {
            return response()->json(['message' => 'Provide a trade or estimator fields.'], 422);
        }

        // Headline numbers: the trade quote wins; else the first money/hours calcs.
        $moneyCalc = collect($custom['results'])->firstWhere('format', 'money');
        $hoursCalc = collect($custom['results'])->firstWhere('format', 'hours');
        $costCents = $quote['cost_low_cents'] ?? (int) round(($moneyCalc['raw'] ?? 0) * 100);
        $costHighCents = $quote['cost_high_cents'] ?? $costCents;
        $hours = $quote['hours'] ?? (float) ($hoursCalc['raw'] ?? 0);

        $estimate = Estimate::create([
            'site_id' => $site->id,
            'estimator_id' => $estimator?->id,
            'reference' => Estimate::newReference(),
            'trade' => $data['trade'] ?? ($estimator?->slug ?? 'custom'),
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'inputs' => ((array) ($data['inputs'] ?? [])) + $fieldValues,
            'results' => $custom['results'] ?: null,
            'cost_low_cents' => $costCents,
            'cost_high_cents' => $costHighCents,
            'currency' => $quote['currency'] ?? $custom['currency'],
            'hours' => $hours,
            'completion' => $quote['completion'] ?? ($hoursCalc['formatted'] ?? ''),
            'ip_address' => $request->ip(),
        ]);

        $serviceName = $quote['trade_name'] ?? ($estimator?->name ?? 'Custom estimate');
        $headline = $quote['cost_label'] ?? ($moneyCalc['formatted'] ?? '—');

        // CRM funnel: the requester becomes (or updates) a Contact.
        try {
            Contact::capture($site, $data['name'], $data['email'], $data['phone'] ?? null,
                "Requested a {$serviceName} ({$estimate->reference}) — {$headline}.");
        } catch (\Throwable $e) {
            report($e);
        }

        // ── Emails: visitor gets the ADMIN-DRAFTED quote email; the admin
        //    gets a submission notification. Best-effort — never blocks the lead.
        $fields = array_filter([
            'reference' => $estimate->reference,
            'service' => $serviceName,
            'estimated_cost' => $headline,
            'estimated_completion' => $estimate->completion,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ] + collect($custom['results'])->mapWithKeys(fn ($r) => [Str::slug($r['name'], '_') => $r['formatted']])->all());
        try {
            if ($owner = $site->user?->email) {
                Mail::to($owner)->send(new FormSubmissionNotification($site, $serviceName.' request', $fields, url("{$site->name}/estimates")));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            Mail::to($data['email'])->send(new EstimateQuoteMail($site, $estimate, $custom['results']));
        } catch (\Throwable $e) {
            report($e);
        }

        // ── Dashboard notification: an Alert for the team + the activity feed.
        try {
            Alert::create([
                'site_id' => $site->id,
                'level' => 'info',
                'type' => 'estimate',
                'audience' => 'all',
                'title' => "New estimate request {$estimate->reference}",
                'body' => "{$data['name']} requested {$serviceName} — {$headline}.",
                'link' => url("{$site->name}/estimates"),
                'meta' => ['estimate_id' => $estimate->id, 'reference' => $estimate->reference],
            ]);
            ActivityLogger::log($site->id, 'estimate', 'responded',
                "Estimate request {$estimate->reference} received", [
                    'entity_id' => $estimate->id,
                    'description' => "{$data['name']} · {$serviceName} · {$headline}",
                    'url' => '/estimates',
                    'meta' => ['email' => $data['email']],
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'reference' => $estimate->reference,
            'results' => $custom['results'],
            'message' => 'Estimate saved — we emailed you a copy and will be in touch shortly.',
        ] + ($quote ?: []), 201);
    }
}
