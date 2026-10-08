<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Component as SiteComponent;
use App\Models\Media as MediaModel;
use App\Models\Node;
use App\Models\PageAttribute;
use App\Models\Post;
use App\Models\Site;
use App\Services\MediaStore;
use App\Support\SiteContentCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaPage extends Component
{
    use WithFileUploads, WithLayoutMode, WithPagination;

    public const PER_PAGE = 16; // 4 × 4 grid

    public Site $site;

    public string $search = '';

    public string $activeTab = 'all';   // all | image | video | document

    /** Right-rail shortcut: only images without alt text. */
    public bool $missingAlt = false;

    /** Preview lightbox. */
    public ?string $previewId = null;

    /** Drag-and-drop queue (multiple files). */
    public array $uploads = [];

    // Manual "add by URL" / edit form
    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?string $editingId = null;

    public ?string $deletingId = null;

    public string $name = '';

    public string $file_type = 'image';

    public string $url = '';

    public string $size = '';

    public string $alt_text = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('media', 'grid');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['all', ...MediaModel::TYPES], true) ? $tab : 'all';
        $this->missingAlt = false;
        $this->resetPage();
    }

    public function showMissingAlt(): void
    {
        $this->activeTab = 'image';
        $this->missingAlt = true;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function preview(string $id): void
    {
        $this->previewId = $id;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function getPreviewItemProperty(): ?MediaModel
    {
        return $this->previewId
            ? MediaModel::where('site_id', $this->site->id)->find($this->previewId)
            : null;
    }

    /** Auto-process files as soon as they're dropped/selected. */
    public function updatedUploads(): void
    {
        // The dropzone's progress panel waits for one of these two events.
        try {
            $this->validateUploads();
        } catch (ValidationException $e) {
            // Show the errors without throwing — a thrown exception drops the event.
            $this->uploads = [];
            $this->setErrorBag($e->validator->errors());
            $this->dispatch('media-upload-failed');

            return;
        }
        $store = app(MediaStore::class);
        $sub = $this->site->user->currentSubscription();
        $count = 0;
        $skipped = 0;
        foreach ($this->uploads as $file) {
            // Stop when the account's storage quota can't fit the next file.
            if (! $sub->canStore((int) $file->getSize())) {
                $skipped++;

                continue;
            }
            $store->store($this->site, $file);
            $count++;
        }

        $this->uploads = [];
        $this->dispatch('media-uploaded', count: $count, skipped: $skipped);
        if ($count > 0) {
            $this->successMessage = $count.' '.str('file')->plural($count).' uploaded.';
        }
        if ($skipped > 0) {
            $limit = $sub->storageLimitMb();
            $this->dispatch('upgrade-required',
                reason: $skipped.' '.str('file')->plural($skipped).' skipped — your plan includes '.
                    ($limit === null ? 'unlimited storage' : $limit.' MB of asset storage').' and it\'s full. Upgrade for more space, or remove some assets.',
                cta: 'Get more storage');
        }
    }

    private function validateUploads(): void
    {
        $this->validate([
            'uploads' => ['array'],
            'uploads.*' => ['file', 'max:51200'], // 50 MB each
        ], [
            'uploads.*.max' => 'Each file must be 50 MB or smaller.',
        ]);
    }

    public function render()
    {
        $base = MediaModel::where('site_id', $this->site->id);

        $mediaItems = (clone $base)
            ->when($this->activeTab !== 'all', fn ($q) => $q->where('file_type', $this->activeTab))
            ->when($this->missingAlt, fn ($q) => $q->where(fn ($w) => $w->whereNull('alt_text')->orWhere('alt_text', '')))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('alt_text', 'like', '%'.$this->search.'%');
            }))
            ->latest()
            ->paginate(self::PER_PAGE);

        $counts = [
            'all' => (clone $base)->count(),
            'image' => (clone $base)->where('file_type', 'image')->count(),
            'video' => (clone $base)->where('file_type', 'video')->count(),
            'audio' => (clone $base)->where('file_type', 'audio')->count(),
            'font' => (clone $base)->where('file_type', 'font')->count(),
            'document' => (clone $base)->where('file_type', 'document')->count(),
        ];

        $recent = (clone $base)->where('created_at', '>=', now()->startOfWeek())->count();

        $sub = $this->site->user->currentSubscription();
        $limitBytes = $sub->storageLimitBytes();
        $usedBytes = $sub->storageUsedBytes();
        $storage = [
            'used' => $usedBytes,
            'limit' => $limitBytes,                                   // null = unlimited
            'used_h' => MediaModel::humanSize($usedBytes),
            'limit_h' => $limitBytes === null ? '∞' : MediaModel::humanSize($limitBytes),
            'free_h' => $limitBytes === null ? '∞' : MediaModel::humanSize(max(0, $limitBytes - $usedBytes)),
            'pct' => $limitBytes ? min(100, (int) round($usedBytes / $limitBytes * 100)) : 0,
        ];

        return view('livewire.media-page', [
            'mediaItems' => $mediaItems,
            'counts' => $counts,
            'recent' => $recent,
            'storage' => $storage,
            'summary' => $this->summary($base),
        ]);
    }

    /**
     * Right-rail summary: space by type, what needs attention (alt text,
     * heavy images, files nothing seems to use) and the largest files.
     * Cached per site + content version, so typing in search stays cheap.
     */
    private function summary($base): array
    {
        $key = 'media-summary:'.$this->site->id.':'.SiteContentCache::version($this->site->id).':'.(clone $base)->count().':'.(clone $base)->max('updated_at');

        return Cache::remember($key, 300, function () use ($base) {
            $byType = (clone $base)->selectRaw('file_type, count(*) as n, coalesce(sum(bytes),0) as b')->groupBy('file_type')->get()
                ->map(fn ($r) => ['type' => $r->file_type, 'n' => (int) $r->n, 'bytes' => (int) $r->b])
                ->sortByDesc('bytes')->values()->all();

            return [
                'byType' => $byType,
                'totalBytes' => array_sum(array_column($byType, 'bytes')),
                'missingAlt' => (clone $base)->where('file_type', 'image')->where(fn ($w) => $w->whereNull('alt_text')->orWhere('alt_text', ''))->count(),
                'heavy' => (clone $base)->where('file_type', 'image')->where('bytes', '>', 1024 * 1024)->count(),
                'largest' => (clone $base)->where('bytes', '>', 0)->orderByDesc('bytes')->limit(5)->get(['id', 'name', 'file_type', 'bytes', 'url'])
                    ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->file_type, 'size' => MediaModel::humanSize((int) $m->bytes)])->all(),
                'unused' => $this->unusedFiles($base),
            ];
        });
    }

    /**
     * Files no content seems to reference — by file name (covers "@media/…"
     * refs and full URLs) across sections, posts and site/page settings.
     * A hint for clean-up, not a guarantee (templates may hard-code assets).
     */
    private function unusedFiles($base): array
    {
        $siteId = $this->site->id;
        $hay = mb_strtolower(implode("\n", array_merge(
            Node::whereIn('component_id', SiteComponent::where('site_id', $siteId)->select('id'))->pluck('value')->all(),
            Post::where('site_id', $siteId)->get(['body', 'cover_image'])->flatMap(fn ($p) => [$p->body, $p->cover_image])->all(),
            $this->site->siteAttributes()->pluck('value')->all(),
            PageAttribute::whereIn('page_id', $this->site->pages()->select('id'))->pluck('value')->all(),
        )));
        $unused = (clone $base)->latest()->get(['id', 'name', 'url', 'file_type', 'bytes'])
            ->reject(function ($m) use ($hay) {
                $file = mb_strtolower(basename((string) $m->url));
                $name = mb_strtolower((string) $m->name);

                return ($file !== '' && str_contains($hay, $file)) || ($name !== '' && str_contains($hay, $name));
            });

        return [
            'count' => $unused->count(),
            'bytes' => MediaModel::humanSize((int) $unused->sum('bytes')),
            'sample' => $unused->take(4)->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()->all(),
        ];
    }

    // ── Manual add-by-URL / edit ──────────────────────────────────

    public function openCreate(): void
    {
        $this->reset(['name', 'file_type', 'url', 'size', 'alt_text', 'editingId']);
        $this->file_type = 'image';
        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        $media = MediaModel::where('site_id', $this->site->id)->findOrFail($id);
        $this->editingId = $id;
        $this->name = $media->name;
        $this->file_type = $media->file_type;
        $this->url = $media->url;
        $this->size = $media->size ?? '';
        $this->alt_text = $media->alt_text ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:2',
            'file_type' => 'required|in:'.implode(',', MediaModel::TYPES),
            'url' => 'required',
            'size' => 'nullable|max:20',
            'alt_text' => 'nullable|max:255',
        ]);

        $url = $this->url;
        $size = $this->size;
        $fileType = $this->file_type;

        // If an external URL was given, pull the file into THIS site's folder
        // so the media library is self-contained. Falls back to a reference link
        // if the download isn't possible.
        if (Str::startsWith($url, ['http://', 'https://'])) {
            $fetched = $this->fetchIntoSiteFolder($url);
            if ($fetched) {
                $url = $fetched['url'];
                $size = $size ?: $fetched['size'];
                $fileType = $fetched['type'] ?? $fileType;
            }
        }

        $payload = [
            'name' => $this->name,
            'file_type' => $fileType,
            'url' => $url,
            'size' => $size,
            'alt_text' => $this->alt_text,
        ];

        if ($this->editingId) {
            MediaModel::where('site_id', $this->site->id)->findOrFail($this->editingId)->update($payload);
            $this->successMessage = 'Media updated.';
        } else {
            MediaModel::create($payload + ['site_id' => $this->site->id]);
            $this->successMessage = Str::startsWith($url, '/storage/') ? 'Media downloaded to your library.' : 'Media added (external link).';
        }

        $this->showModal = false;
        $this->reset(['name', 'file_type', 'url', 'size', 'alt_text', 'editingId']);
    }

    /**
     * Download a remote URL into the selected site's media folder.
     * Returns ['url','size','type'] on success, or null to keep it as a link.
     */
    private function fetchIntoSiteFolder(string $remoteUrl): ?array
    {
        try {
            $resp = Http::timeout(20)->withOptions(['stream' => false])->get($remoteUrl);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $resp->successful()) {
            return null;
        }

        $body = $resp->body();
        $bytes = strlen($body);
        if ($bytes === 0 || $bytes > 52428800) { // empty or >50MB → keep as link
            return null;
        }

        $mime = strtok((string) $resp->header('Content-Type'), ';') ?: null;
        $type = MediaModel::typeFromMime($mime);

        // Only ingest real media types; HTML/other → keep as external reference.
        if ($mime && str_starts_with($mime, 'text/html')) {
            return null;
        }

        // Derive an extension from the URL path, else from the MIME type.
        $ext = pathinfo((string) parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION)
            ?: $this->extFromMime($mime);

        $path = 'media/'.$this->site->name.'/'.Str::random(40).($ext ? '.'.$ext : '');
        Storage::disk('public')->put($path, $body);

        return [
            'url' => Storage::url($path),
            'size' => MediaModel::humanSize($bytes),
            'type' => $type,
        ];
    }

    private function extFromMime(?string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            default => null,
        };
    }

    /** Delete one media item — confirmation happens in the shared modal (data-confirm). */
    public function deleteMedia(string $id): void
    {
        $this->deletingId = $id;
        $this->delete();
    }

    public function delete(): void
    {
        $media = MediaModel::where('site_id', $this->site->id)->findOrFail($this->deletingId);

        // Remove the stored file too (only if it lives on our public disk).
        if (str_starts_with($media->url, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $media->url));
        }

        $media->delete();
        $this->showDeleteModal = false;
        $this->deletingId = 0;
        $this->successMessage = 'Media deleted.';
    }
}
