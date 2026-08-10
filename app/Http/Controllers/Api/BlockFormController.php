<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/sites/{siteName}/block-form/{blockId}
 *
 * Submission endpoint for BlockKit `form` blocks. The block tree IS the form
 * schema — the form's input/textarea/select/checkbox descendants define the
 * accepted field names, types and required flags. Valid submissions land in
 * the platform inbox (FormSubmission) when submit_action is `collect`.
 */
class BlockFormController extends Controller
{
    public function store(Request $request, string $siteName, string $blockId): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $form = Block::whereKey($blockId)->where('type', 'form')
            ->whereHas('page', fn ($q) => $q->where('site_id', $site->id))
            ->first();
        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        // The tree defines the schema: collect field descendants.
        $fields = $this->fieldDefs($form);
        if ($fields === []) {
            return response()->json(['message' => 'This form has no fields.'], 422);
        }

        $rules = [];
        foreach ($fields as $f) {
            $r = [$f['required'] ? 'required' : 'nullable'];
            $r[] = match ($f['field_type'] ?? 'text') {
                'email' => 'email',
                'number' => 'numeric',
                'date' => 'date',
                default => 'string',
            };
            if ($f['type'] === 'checkbox') {
                $r = [$f['required'] ? 'accepted' : 'nullable'];
            }
            $rules[$f['name']] = $r;
        }
        $validated = $request->validate($rules);

        // Submissions land in the platform inbox: each BlockKit form owns a
        // Form record (auto-created on first submission) so responses show up
        // in the existing Forms UI alongside classic forms.
        $inbox = Form::firstOrCreate(
            ['site_id' => $site->id, 'name' => 'blockkit-'.$form->id],
            [
                'title' => (string) data_get($form->meta, 'label', 'Form'),
                'description' => 'BlockKit form — schema lives in the page block tree.',
                'fields' => collect($fields)->map(fn ($f) => [
                    'key' => $f['name'], 'label' => $f['name'], 'type' => $f['field_type'] ?? $f['type'],
                ])->values()->all(),
                'is_active' => true,
            ],
        );
        FormResponse::create([
            'form_id' => $inbox->id,
            'fields' => collect($fields)->mapWithKeys(fn ($f) => [$f['name'] => $validated[$f['name']] ?? null])->all(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => (string) data_get($form->props, 'success_message', 'Thanks — we got your message.'),
        ], 201);
    }

    /** @return array<int,array{name:string,type:string,field_type:?string,required:bool}> */
    private function fieldDefs(Block $block): array
    {
        $out = [];
        foreach ($block->children as $child) {
            if (in_array($child->type, ['input', 'textarea', 'select', 'checkbox'], true)) {
                $name = (string) data_get($child->props, 'name', '');
                if ($name !== '') {
                    $out[] = [
                        'name' => $name,
                        'type' => $child->type,
                        'field_type' => data_get($child->props, 'field_type'),
                        'required' => (bool) data_get($child->props, 'required', false),
                    ];
                }
            } elseif ($child->isLayout()) {
                $out = array_merge($out, $this->fieldDefs($child));
            }
        }

        return $out;
    }
}
