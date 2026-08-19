<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Form;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Content history: snapshots a content model's editable payload BEFORE every
 * mutation and can restore any stored version. Snapshots are id-independent
 * (component nodes stored as a nested tree), identical consecutive payloads
 * are skipped, and the store is pruned to the last N per subject
 * (config site_connect.versions_keep). Capture is best-effort — a versioning
 * failure must never block a save.
 */
class ContentVersioner
{
    /** Snapshot the subject's current state; call before changing it. */
    public function capture(Model $subject, ?string $editor = null): void
    {
        try {
            [$type, $payload, $label] = $this->serialize($subject);

            $latest = ContentVersion::where('subject_type', $type)->where('subject_id', $subject->id)
                ->latest('created_at')->latest('id')->first();
            // Canonical compare — MySQL's JSON type reorders object keys.
            if ($latest && $this->canonical($latest->payload) === $this->canonical($payload)) {
                return; // nothing changed since the last snapshot
            }

            ContentVersion::create([
                'site_id' => $subject->site_id,
                'subject_type' => $type,
                'subject_id' => $subject->id,
                'payload' => $payload,
                'label' => $label,
                'created_by' => $editor,
            ]);

            $keep = max(3, (int) config('site_connect.versions_keep', 5));
            $stale = ContentVersion::where('subject_type', $type)->where('subject_id', $subject->id)
                ->orderByDesc('created_at')->orderByDesc('id')->skip($keep)->take(50)->pluck('id');
            if ($stale->isNotEmpty()) {
                ContentVersion::whereIn('id', $stale)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('content-version capture failed', ['subject' => $subject::class, 'id' => $subject->id, 'error' => $e->getMessage()]);
        }
    }

    /** Write a stored version back onto its subject. The pre-revert state is captured first. */
    public function restore(ContentVersion $version): void
    {
        $subject = $this->subjectOf($version);
        abort_unless($subject !== null, 404, 'The content this version belongs to no longer exists.');

        $this->capture($subject, 'pre-revert');
        $payload = $version->payload;

        match ($version->subject_type) {
            'component' => $this->restoreComponent($subject, $payload),
            'collection' => $this->restoreCollection($subject, $payload),
            'form' => $subject->update(['title' => $payload['title'] ?? $subject->title,
                'fields' => $payload['fields'] ?? [], 'is_active' => $payload['is_active'] ?? true]),
            'post' => $subject->update(['title' => $payload['title'] ?? $subject->title,
                'excerpt' => $payload['excerpt'] ?? null, 'body' => $payload['body'] ?? '',
                'status' => $payload['status'] ?? $subject->status]),
            default => abort(422, 'Unknown version type.'),
        };
    }

    public function subjectOf(ContentVersion $version): ?Model
    {
        $q = fn ($model) => $model::where('site_id', $version->site_id)->find($version->subject_id);

        return match ($version->subject_type) {
            'component' => $q(Component::class),
            'collection' => $q(Collection::class),
            'form' => $q(Form::class),
            'post' => $q(Post::class),
            default => null,
        };
    }

    /** @return array{0:string,1:array,2:string} [type, payload, label] */
    private function serialize(Model $subject): array
    {
        return match (true) {
            $subject instanceof Component => ['component',
                ['nodes' => $this->treeOf($subject)],
                $subject->name.' · '.$subject->nodes()->count().' node(s)'],
            $subject instanceof Collection => ['collection',
                ['fields' => $subject->fields ?? [],
                    'items' => $subject->items()->orderBy('id')->get()
                        ->map(fn (CollectionItem $i) => ['status' => $i->status, 'data' => $i->data ?? []])->values()->all()],
                $subject->name.' · '.$subject->items()->count().' item(s)'],
            $subject instanceof Form => ['form',
                ['title' => $subject->title, 'fields' => $subject->fields ?? [], 'is_active' => (bool) $subject->is_active],
                $subject->name.' · '.count($subject->fields ?? []).' field(s)'],
            $subject instanceof Post => ['post',
                ['title' => $subject->title, 'excerpt' => $subject->excerpt, 'body' => $subject->body, 'status' => $subject->status],
                $subject->title],
            default => throw new \InvalidArgumentException('Unversionable subject: '.$subject::class),
        };
    }

    /** Key-order-independent representation for change detection. */
    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                ksort($value);
            }
            $value = array_map(fn ($v) => is_array($v) ? json_decode($this->canonical($v), true) : $v, $value);
        }

        return json_encode($value);
    }

    /** Nested, id-free node tree (labels/types/values/children). */
    private function treeOf(Component $component): array
    {
        $strip = function (array $rows) use (&$strip): array {
            return array_map(fn ($n) => [
                'label' => $n['label'], 'type' => $n['type'], 'value' => (string) $n['value'],
                'description' => $n['description'] ?? null,
                'children' => $strip($n['children'] ?? []),
            ], $rows);
        };

        return $strip(Component::buildNodeTree($component->nodes()->get()));
    }

    private function restoreComponent(Component $component, array $payload): void
    {
        $component->nodes()->delete();
        $order = 0;
        $create = function (array $rows, string $parent) use (&$create, $component, &$order): void {
            foreach ($rows as $row) {
                $node = $component->nodes()->create([
                    'label' => $row['label'], 'type' => $row['type'], 'value' => $row['value'] ?? '',
                    'description' => $row['description'] ?? null,
                    'parent' => $parent, 'order' => $order++,
                ]);
                $create($row['children'] ?? [], (string) $node->id);
            }
        };
        $create($payload['nodes'] ?? [], '0');
    }

    private function restoreCollection(Collection $collection, array $payload): void
    {
        $collection->update(['fields' => $payload['fields'] ?? []]);
        $collection->items()->delete();
        foreach ($payload['items'] ?? [] as $item) {
            CollectionItem::create([
                'collection_id' => $collection->id,
                'site_id' => $collection->site_id,
                'status' => $item['status'] ?? 'published',
                'data' => $item['data'] ?? [],
            ]);
        }
    }
}
