<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Forms CRUD API — define a form once, then let the client site fetch its
 * schema (GET /form/{name}) and submit against it (POST /form/{name}).
 *
 *   GET    /api/sites/{site}/forms         → active forms + field schemas (public)
 *   POST   /api/sites/{site}/forms         → create  (Bearer token · forms.manage)
 *   PATCH  /api/sites/{site}/forms/{name}  → update  (Bearer token · forms.manage)
 *   DELETE /api/sites/{site}/forms/{name}  → delete  (Bearer token · forms.manage)
 *
 * The form name is slugified and unique per site — it is the handle the
 * schema + submission endpoints address the form by. When a fields array is
 * present on a write it REPLACES the schema wholesale.
 */
class FormApiController extends Controller
{
    private function publicSite(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    private function manageableSite(Request $request, string $siteName): Site
    {
        $site = $this->publicSite($siteName);
        $user = $request->attributes->get('api_token_user');
        abort_unless($user && $site->allows($user, 'forms.manage'), 403, 'This token cannot manage forms on this site.');

        return $site;
    }

    private function record(Form $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'title' => $form->displayTitle(),
            'description' => $form->description,
            'is_active' => (bool) $form->is_active,
            'fields' => $form->fields ?? [],
            'responses' => $form->responses()->count(),
            'submit_url' => route('api.form', ['siteName' => $form->site->name, 'formName' => $form->name]),
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];
    }

    public function index(string $siteName): JsonResponse
    {
        $site = $this->publicSite($siteName);

        return response()->json([
            'forms' => Form::where('site_id', $site->id)->where('is_active', true)->get()
                ->map(fn (Form $f) => collect($this->record($f))->except(['responses'])->all())->values(),
        ]);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'fields' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:60'],
            'fields.*.key' => ['required', 'string', 'max:60'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['sometimes', 'in:'.implode(',', Form::FIELD_TYPES)],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['sometimes', 'array', 'max:60'],
            'fields.*.options.*' => ['string', 'max:255'],
            'fields.*.min' => ['nullable', 'numeric'],
            'fields.*.max' => ['nullable', 'numeric'],
        ]);
    }

    /** Normalise a fields array to the canonical stored shape. */
    private function cleanFields(array $fields): array
    {
        return collect($fields)->map(fn (array $f) => array_filter([
            'key' => Str::slug($f['key'], '_'),
            'label' => $f['label'] ?? ucwords(str_replace(['-', '_'], ' ', $f['key'])),
            'type' => $f['type'] ?? 'text',
            'required' => (bool) ($f['required'] ?? false),
            'placeholder' => $f['placeholder'] ?? null,
            'options' => $f['options'] ?? null,
            'min' => $f['min'] ?? null,
            'max' => $f['max'] ?? null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName);
        $data = $this->validated($request, creating: true);
        $name = Str::slug($data['name'], '-');

        abort_if(Form::where('site_id', $site->id)->where('name', $name)->exists(), 422, 'A form with this name already exists on the site.');

        $form = Form::create([
            'site_id' => $site->id,
            'name' => $name,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'fields' => $this->cleanFields($data['fields']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['ok' => true, 'form' => $this->record($form)], 201);
    }

    public function update(Request $request, string $siteName, string $formName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName);
        $form = Form::where('site_id', $site->id)->where('name', $formName)->firstOrFail();
        $data = $this->validated($request, creating: false);

        if (isset($data['name'])) {
            $newName = Str::slug($data['name'], '-');
            if (Form::where('site_id', $site->id)->where('name', $newName)->whereKeyNot($form->id)->exists()) {
                abort(422, 'A form with this name already exists on the site.');
            }
            $form->name = $newName;
        }
        $form->fill(collect($data)->only(['title', 'description', 'is_active'])->all());
        if (isset($data['fields'])) {
            $form->fields = $this->cleanFields($data['fields']);
        }
        $form->save();

        return response()->json(['ok' => true, 'form' => $this->record($form)]);
    }

    public function destroy(Request $request, string $siteName, string $formName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName);
        $form = Form::where('site_id', $site->id)->where('name', $formName)->firstOrFail();
        $form->responses()->delete();
        $form->delete();

        return response()->json(['ok' => true]);
    }
}
