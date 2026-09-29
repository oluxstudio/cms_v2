<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Jobs\ProcessTemplateUpload;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\TemplateUpload;
use App\Services\TemplatePublisher;
use App\Support\TemplatePaths;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Platform admin › Templates: the whole catalog (who uses what, sales,
 * builds), every client upload across accounts, and the review queue.
 */
class PlatformTemplatesPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const TABS = ['catalog', 'uploads', 'review'];

    #[Url(as: 'tab')]
    public string $tab = 'catalog';

    #[Url(as: 'q')]
    public string $q = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'source')]
    public string $source = '';

    #[Url(as: 'sort')]
    public string $sort = 'installs';

    /** Detail drawer. */
    public ?string $openId = null;

    /** Inline edit modal. */
    public ?string $editingId = null;

    public array $edit = [
        'name' => '', 'price' => '', 'category' => '', 'short_description' => '',
        'description' => '', 'tags' => '', 'features' => [],
    ];

    /** Upload-a-template drawer. */
    public bool $uploading = false;

    public $appZip = null;

    public string $uploadName = '';

    public ?string $rejectingId = null;

    public string $rejectReason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'catalog';
        }
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['q', 'status', 'source', 'sort', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'catalog';
        $this->resetPage();
    }

    public function open(string $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    /** Published ↔ draft. Client uploads stay private to their owner. */
    public function togglePublished(string $id): void
    {
        $t = Template::findOrFail($id);
        if ($t->status === 'private') {
            $this->dispatch('toast', level: 'error', title: 'Private template', message: 'Client uploads stay private to their account.');

            return;
        }
        $publish = $t->status !== 'published';
        $t->update($publish
            ? ['status' => 'published', 'published_at' => $t->published_at ?? now(), 'rejection_reason' => null]
            : ['status' => 'draft']);
        $this->dispatch('toast', level: 'success', title: $publish ? 'Published' : 'Unpublished',
            message: $t->name.($publish ? ' is in the Templates store.' : ' is hidden from the store. Sites already using it keep it.'));
    }

    public function startEdit(string $id): void
    {
        $t = Template::findOrFail($id);
        $this->editingId = $id;
        $this->edit = [
            'name' => (string) $t->name,
            'price' => $t->price_cents ? number_format($t->price_cents / 100, 2, '.', '') : '',
            'category' => (string) $t->category,
            'short_description' => (string) $t->short_description,
            'description' => (string) $t->description,
            'tags' => implode(', ', (array) $t->tags),
            'features' => array_values((array) $t->required_features),
        ];
    }

    public function saveEdit(): void
    {
        $this->validate([
            'edit.name' => ['required', 'string', 'max:80'],
            'edit.price' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'edit.category' => ['nullable', 'string', 'max:60'],
            'edit.short_description' => ['nullable', 'string', 'max:200'],
            'edit.description' => ['nullable', 'string', 'max:5000'],
            'edit.tags' => ['nullable', 'string', 'max:300'],
            'edit.features' => ['array'],
            'edit.features.*' => ['string', fn ($a, $v, $fail) => FeatureRegistry::exists($v) ? null : $fail('Unknown feature.')],
        ], ['edit.name.required' => 'Give the template a name.']);
        $t = Template::findOrFail($this->editingId);
        $t->update([
            'name' => trim($this->edit['name']),
            'price_cents' => (int) round(((float) ($this->edit['price'] ?: 0)) * 100),
            'category' => trim($this->edit['category']) ?: null,
            'short_description' => trim($this->edit['short_description']) ?: null,
            'description' => trim($this->edit['description']) ?: null,
            'tags' => collect(explode(',', $this->edit['tags']))->map(fn ($s) => trim($s))->filter()->unique()->values()->all(),
            'required_features' => array_values(array_unique($this->edit['features'])),
        ]);
        $this->editingId = null;
        $this->dispatch('toast', level: 'success', title: 'Saved', message: $t->name.' updated.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function openUpload(): void
    {
        $this->reset('appZip', 'uploadName');
        $this->resetErrorBag();
        $this->uploading = true;
    }

    public function closeUpload(): void
    {
        $this->uploading = false;
    }

    /** Upload a Nuxt app as a new Olux Studio store template (arrives as a draft). */
    public function uploadTemplate(): void
    {
        $this->validate([
            'appZip' => ['required', 'file', 'mimes:zip', 'max:'.(int) config('templates.uploads.max_zip_kb', 61440)],
            'uploadName' => ['nullable', 'string', 'max:80'],
        ], [
            'appZip.required' => 'Choose a .zip of the Nuxt app first.',
            'appZip.mimes' => 'Upload a .zip file.',
            'appZip.max' => 'The zip is too large (60 MB max). Leave out node_modules, .nuxt and .output.',
        ]);

        $upload = TemplateUpload::create([
            'user_id' => Auth::id(),
            'for_store' => true,
            'key' => TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10)),
            'name' => trim($this->uploadName) ?: null,
            'original_filename' => Str::limit($this->appZip->getClientOriginalName(), 180, ''),
            'status' => TemplateUpload::QUEUED,
            'step' => 'Waiting to start',
        ]);
        File::ensureDirectoryExists(dirname($upload->zipPath()));
        File::copy($this->appZip->getRealPath(), $upload->zipPath());
        ProcessTemplateUpload::dispatch($upload->id);

        $this->uploading = false;
        $this->reset('appZip', 'uploadName');
        $this->setTab('uploads');
        $this->dispatch('toast', level: 'success', title: 'Upload received',
            message: 'It\'s being checked and built. When it\'s ready it appears in the catalog as a draft for you to publish.');
    }

    public function approve(string $id, TemplatePublisher $publisher): void
    {
        $t = Template::where('status', 'in_review')->findOrFail($id);
        $publisher->approve($t);
        $this->dispatch('toast', level: 'success', title: 'Approved', message: $t->name.' is now in the Templates store.');
    }

    public function startReject(string $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function reject(TemplatePublisher $publisher): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:5', 'max:500']],
            ['rejectReason.required' => 'Tell the creator what to change.']);
        $t = Template::where('status', 'in_review')->findOrFail($this->rejectingId);
        $publisher->reject($t, $this->rejectReason);
        $this->rejectingId = null;
        $this->dispatch('toast', level: 'success', title: 'Sent back', message: $t->name.' was returned to its creator.');
    }

    /** Remove a failed upload, or a finished one no site uses, with its files. */
    public function deleteUpload(string $id): void
    {
        $u = TemplateUpload::findOrFail($id);
        if ($u->inProgress()) {
            return;
        }
        if ($u->template_id && SiteTemplate::where('template_id', $u->template_id)->exists()) {
            $this->dispatch('toast', level: 'error', title: 'In use', message: 'A site uses this template, so it can\'t be deleted.');

            return;
        }
        if ($t = $u->template) {
            TemplateEntitlement::where('template_id', $t->id)->delete();
            $t->versions()->delete();
            $u->update(['template_id' => null]);
            $t->delete();
        }
        File::deleteDirectory(TemplatePaths::uploadsRoot().'/'.$u->key);
        File::deleteDirectory(TemplatePaths::shellDir($u->key));
        File::delete($u->zipPath());
        $u->delete();
        $this->dispatch('toast', level: 'success', title: 'Upload deleted', message: 'Removed with its files.');
    }

    public function render()
    {
        $since30 = now()->subDays(30);
        $usedBy = SiteTemplate::whereNotNull('applied_at')->whereNotNull('template_id')
            ->selectRaw('template_id, count(*) as n')->groupBy('template_id')->pluck('n', 'template_id');
        $revenue = TemplatePurchase::where('status', 'paid')
            ->selectRaw('template_id, sum(price_cents) as gross, count(*) as n')->groupBy('template_id')
            ->get()->keyBy('template_id');

        $stats = [
            'published' => Template::where('status', 'published')->count(),
            'private' => Template::where('status', 'private')->count(),
            'in_review' => Template::where('status', 'in_review')->count(),
            'installs' => (int) Template::sum('installs_count'),
            'sites_using' => (int) $usedBy->sum(),
            'revenue_30d' => (int) TemplatePurchase::where('status', 'paid')->where('created_at', '>=', $since30)->sum('price_cents'),
            'fees_30d' => (int) TemplatePurchase::where('status', 'paid')->where('created_at', '>=', $since30)->sum('platform_fee_cents'),
            'building' => TemplateUpload::whereIn('status', [TemplateUpload::QUEUED, TemplateUpload::SCANNING, TemplateUpload::BUILDING])->count(),
            'failed_7d' => TemplateUpload::where('status', TemplateUpload::FAILED)->where('updated_at', '>=', now()->subDays(7))->count(),
        ];

        $catalog = null;
        $uploads = null;
        $review = collect();
        if ($this->tab === 'catalog') {
            $query = Template::query()->with('creator', 'user')->withCount('versions')
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")
                    ->orWhere('slug', 'like', "%{$this->q}%")->orWhere('builtin_key', 'like', "%{$this->q}%")))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->when($this->source !== '', fn ($q) => $q->where('source', $this->source));
            match ($this->sort) {
                'newest' => $query->latest(),
                'name' => $query->orderBy('name'),
                'revenue' => $query->orderByDesc(TemplatePurchase::selectRaw('coalesce(sum(price_cents), 0)')
                    ->whereColumn('template_purchases.template_id', 'templates.id')->where('status', 'paid')),
                default => $query->orderByDesc('installs_count')->orderBy('name'),
            };
            $catalog = $query->paginate(15);
        } elseif ($this->tab === 'uploads') {
            $uploads = TemplateUpload::with('user', 'site', 'template')
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")
                    ->orWhere('original_filename', 'like', "%{$this->q}%")->orWhere('key', 'like', "%{$this->q}%")))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->latest()->paginate(15);
        } else {
            $review = Template::with('user', 'creator')->where('status', 'in_review')->orderBy('submitted_at')->get();
        }

        $opened = $this->openId ? Template::with(['versions' => fn ($q) => $q->latest(), 'creator', 'user'])->find($this->openId) : null;
        $detail = $opened ? [
            'sites' => SiteTemplate::with('site.user')->where('template_id', $opened->id)->whereNotNull('applied_at')
                ->latest('applied_at')->limit(25)->get(),
            'owners' => TemplateEntitlement::where('template_id', $opened->id)->count(),
            'purchases' => TemplatePurchase::where('template_id', $opened->id)->where('status', 'paid')
                ->selectRaw('count(*) as n, coalesce(sum(price_cents),0) as gross, coalesce(sum(platform_fee_cents),0) as fees')->first(),
            'upload' => TemplateUpload::where('template_id', $opened->id)->latest()->first(),
            'built' => TemplatePaths::hasShell((string) ($opened->builtin_key ?: $opened->slug)),
        ] : null;

        return view('livewire.platform-templates-page', [
            'stats' => $stats,
            'catalog' => $catalog,
            'uploads' => $uploads,
            'review' => $review,
            'usedBy' => $usedBy,
            'revenue' => $revenue,
            'opened' => $opened,
            'detail' => $detail,
            'topInstalled' => Template::whereIn('status', ['published', 'private'])->orderByDesc('installs_count')->limit(5)->get(['id', 'name', 'installs_count', 'status']),
            'recentUploads' => TemplateUpload::with('user')->latest()->limit(5)->get(),
            'anyInProgress' => $stats['building'] > 0,
            'allFeatures' => FeatureRegistry::all(),
        ]);
    }
}
