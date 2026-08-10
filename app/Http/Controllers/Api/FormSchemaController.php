<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Site;
use App\Support\FieldSchemaPresenter;
use Illuminate\Http\JsonResponse;

class FormSchemaController extends Controller
{
    /**
     * GET /api/sites/{siteName}/form/{formName}
     *
     * Returns the form's field definitions in a format the frontend can use to:
     *  - Render the form dynamically (labels, types, placeholders, options)
     *  - Validate client-side before submitting
     *
     * Kept intentionally minimal — no PII, no internal IDs.
     */
    public function show(string $siteName, string $formName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $form = Form::where('site_id', $site->id)
            ->where('name', $formName)
            ->first();

        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        if (! $form->is_active) {
            return response()->json(['message' => 'This form is not accepting submissions.'], 403);
        }

        return response()->json([
            'name' => $form->name,
            'title' => $form->displayTitle(),
            'description' => $form->description,
            'fields' => FieldSchemaPresenter::fields($form->fields ?? []),
        ]);
    }
}
