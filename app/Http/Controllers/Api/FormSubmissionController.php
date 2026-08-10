<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use App\Services\FormDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $response = FormResponse::create([
            'form_id' => $form->id,
            'fields' => $fields,
            'ip_address' => $request->ip(),
        ]);

        // ── 5. Dispatch to the form's enabled delivery channels (email now;
        // SMS/WhatsApp later). Best-effort — never blocks the submission.
        app(FormDelivery::class)->deliver($form, $response, $fields);

        return response()->json([
            'message' => 'Form submitted successfully. Thank you!',
        ], 201);
    }
}
