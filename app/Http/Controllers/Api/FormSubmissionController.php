<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmissionNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class FormSubmissionController extends Controller
{
    /**
     * POST /api/sites/{siteName}/form/{formName}
     *
     * Flow:
     *  1. Resolve site + form — 404 if not found.
     *  2. Reject if form is inactive — 403.
     *  3. Validate submitted data against the predefined field rules — 422 if fails.
     *  4. On success, store only the defined field keys and return 201.
     */
    public function store(Request $request, string $siteName, string $formName): JsonResponse
    {
        // ── 1. Resolve site & form ───────────────────────────────
        $site = Site::where('name', $siteName)->firstOrFail();

        $form = Form::where('site_id', $site->id)
            ->where('name', $formName)
            ->first();

        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        // ── 2. Active check ──────────────────────────────────────
        if (! $form->is_active) {
            return response()->json([
                'message' => 'This form is currently not accepting submissions.',
            ], 403);
        }

        // ── 3. Validate against predefined rules ─────────────────
        $validator = Validator::make(
            $request->all(),
            $form->buildValidationRules(),
            $form->buildValidationMessages()
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The submitted data failed validation.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ── 4. Persist only defined field keys & respond ─────────
        $fields = collect($form->fields ?? [])
            ->mapWithKeys(fn ($f) => [$f['key'] => $request->input($f['key'])])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        FormResponse::create([
            'form_id' => $form->id,
            'fields' => $fields,
            'ip_address' => $request->ip(),
        ]);

        // ── 5. Email BOTH parties (best-effort — mail never blocks the submission):
        // the owner hears about every submission; the visitor gets a receipt copy
        // when the form captured their email address.
        try {
            if ($owner = $site->user?->email) {
                Mail::to($owner)->send(new FormSubmissionNotification($site, $form->name.' form', $fields, url("{$site->name}/forms")));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            if ($visitor = $this->visitorEmail($form, $fields)) {
                Mail::to($visitor)->send(new SubmissionReceipt($site, $form->displayTitle().' form', $fields['name'] ?? null, $fields));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Form submitted successfully. Thank you!',
        ], 201);
    }

    /** The visitor's email from the submission: an email-typed field, else an email-named one. */
    private function visitorEmail(Form $form, array $fields): ?string
    {
        foreach ((array) ($form->fields ?? []) as $def) {
            $key = $def['key'] ?? '';
            if (($def['type'] ?? '') === 'email' && filter_var($fields[$key] ?? '', FILTER_VALIDATE_EMAIL)) {
                return $fields[$key];
            }
        }
        foreach ($fields as $key => $value) {
            if (str_contains(strtolower($key), 'email') && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }
}
