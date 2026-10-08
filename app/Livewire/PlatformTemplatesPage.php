<?php

namespace App\Livewire;

use App\Features\FeatureRegistry;
use App\Jobs\ImportGithubTemplate;
use App\Jobs\InstallTemplateJob;
use App\Jobs\ProcessTemplateUpload;
use App\Models\Site;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateEntitlement;
use App\Models\TemplatePurchase;
use App\Models\TemplateUpload;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\BuiltinTemplateUpdates;
use App\Services\InstallProgress;
use App\Services\SiteToTemplate;
use App\Services\TemplatePublisher;
use App\Services\TemplateUploads\GithubTemplateFetcher;
use App\Support\TemplateAccess;
use App\Support\TemplatePaths;
use App\Templates\TemplateAppRegistry;
use App\Templates\TemplateRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
        'description' => '', 'tags' => '', 'features' => [], 'visibility' => 'public',
    ];

    /** Thumbnail image picked in the edit drawer. */
    public $thumbnail = null;

    /** Assign-a-private-template: account owner's email. */
    public string $assignEmail = '';

    /** Upload-a-template drawer. */
    public bool $uploading = false;

    public $appZip = null;

    public string $uploadName = '';

    /** Add-template drawer: zip | github | site */
    public string $addMode = 'zip';

    public string $repoUrl = '';

    public string $repoBranch = '';

    public string $siteQuery = '';

    public ?string $fromSiteId = null;

    public string $fromSiteName = '';

    public string $newVisibility = 'public';

    /** Add drawer in "new version" mode: the store template being replaced. */
    public ?string $replacingId = null;

    /** A zip that looks like an existing store template (same file / repo name): uploaded as its next version unless $asSeparate. */
    public ?string $zipMatchId = null;

    public bool $asSeparate = false;

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
            message: $t->name.($publish
                ? ($t->isPrivate() ? ' is available to the accounts it\'s assigned to.' : ' is in the Templates store.')
                : ' is hidden from the store. Sites already using it keep it.'));
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
            'visibility' => $t->isPrivate() ? 'private' : 'public',
            'source_repo' => (string) $t->source_repo,
            'source_branch' => (string) $t->source_branch,
            'reset_collections' => array_values((array) $t->reset_collections),
        ];
        $this->assignEmail = '';
        $this->thumbnail = null;
        $this->resetErrorBag(['assignEmail', 'thumbnail']);
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
            'edit.visibility' => ['required', 'in:public,private'],
            'edit.source_repo' => ['nullable', 'string', 'max:300', function ($a, $v, $fail) {
                try {
                    GithubTemplateFetcher::parse((string) $v);
                } catch (\RuntimeException $e) {
                    $fail($e->getMessage());
                }
            }],
            'edit.source_branch' => ['nullable', 'string', 'max:100', 'regex:#^[A-Za-z0-9._/-]+$#'],
            'edit.reset_collections' => ['array'],
            'edit.reset_collections.*' => ['string', 'max:120'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['edit.name.required' => 'Give the template a name.', 'thumbnail.max' => 'The image is too large (4 MB max).']);
        $t = Template::findOrFail($this->editingId);
        if ($this->thumbnail) {
            $this->replaceThumbnail($t, $this->thumbnail->store('template-thumbnails/uploaded', 'public'));
            $this->thumbnail = null;
        }
        // Customers' own uploads are always private to their account.
        $visibility = $t->isAccountUpload() ? 'private' : $this->edit['visibility'];
        $t->update([
            'name' => trim($this->edit['name']),
            'price_cents' => (int) round(((float) ($this->edit['price'] ?: 0)) * 100),
            'category' => trim($this->edit['category']) ?: null,
            'short_description' => trim($this->edit['short_description']) ?: null,
            'description' => trim($this->edit['description']) ?: null,
            'tags' => collect(explode(',', $this->edit['tags']))->map(fn ($s) => trim($s))->filter()->unique()->values()->all(),
            'required_features' => array_values(array_unique($this->edit['features'])),
            'visibility' => $visibility,
            // Only names the template really declares.
            'reset_collections' => array_values(array_intersect(self::collectionNames($t), (array) ($this->edit['reset_collections'] ?? []))) ?: null,
        ]);
        if (self::canNewVersion($t)) {
            $repo = trim((string) ($this->edit['source_repo'] ?? ''));
            $t->update([
                'source_repo' => $repo !== '' ? self::cleanRepo($repo) : null,
                'source_branch' => $repo !== '' ? (trim((string) ($this->edit['source_branch'] ?? '')) ?: null) : null,
            ]);
        }
        Cache::forget('template_catalog_categories');
        $this->editingId = null;
        $this->dispatch('toast', level: 'success', title: 'Saved', message: $t->name.' updated.');
    }

    /**
     * The collections a template declares (its published manifest, else its
     * latest version's payload): name => number of template entries.
     *
     * @return array<string,int>
     */
    public static function templateCollections(Template $t): array
    {
        $key = (string) ($t->builtin_key ?: $t->slug);
        $defs = TemplateAppRegistry::find($key)['manifest']['collections']
            ?? ($t->latestVersion?->payload['collections'] ?? []);

        return collect((array) $defs)->filter(fn ($c) => filled($c['name'] ?? null))
            ->mapWithKeys(fn ($c) => [(string) $c['name'] => count((array) ($c['items'] ?? []))])->all();
    }

    /** @return list<string> */
    private static function collectionNames(Template $t): array
    {
        return array_keys(self::templateCollections($t));
    }

    /** Give a private template to an account (by the account owner's email). */
    public function assignAccount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $this->validate(['assignEmail' => ['required', 'email']], ['assignEmail.required' => 'Enter the account owner\'s email.']);
        $t = Template::findOrFail($this->editingId);
        $account = User::where('email', strtolower(trim($this->assignEmail)))->first();
        if (! $account) {
            $this->addError('assignEmail', 'No account with that email.');

            return;
        }
        TemplateAccess::assign($t, $account, Auth::user());
        $this->assignEmail = '';
        $this->dispatch('toast', level: 'success', title: 'Assigned', message: $t->name.' is now in '.$account->name.'\'s library.');
    }

    public function unassignAccount(string $userId): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $t = Template::findOrFail($this->editingId);
        $account = User::findOrFail($userId);
        TemplateAccess::unassign($t, $account, Auth::user());
        $this->dispatch('toast', level: 'success', title: 'Removed', message: $account->name.' can no longer use '.$t->name.'. Sites already using it keep their design.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->thumbnail = null;
    }

    public function removeThumbnail(): void
    {
        $t = Template::findOrFail($this->editingId);
        $this->replaceThumbnail($t, null);
        $this->dispatch('toast', level: 'success', title: 'Thumbnail removed', message: $t->name.' shows no preview image now.');
    }

    /** Point the template at a new thumbnail (public-disk path or null), deleting an old uploaded one. */
    private function replaceThumbnail(Template $t, ?string $path): void
    {
        $prefix = Storage::disk('public')->url('template-thumbnails/uploaded/');
        if ($t->thumbnail_url && str_starts_with($t->thumbnail_url, $prefix)) {
            Storage::disk('public')->delete('template-thumbnails/uploaded/'.Str::after($t->thumbnail_url, $prefix));
        }
        $t->update(['thumbnail_url' => $path ? Storage::disk('public')->url($path) : null]);
    }

    /** Store templates built from an upload can take a new version (same key, rebuilt). */
    /** A built-in template whose source ships inside the CMS repo (resources/templates/{key}). */
    public static function isRepoBuiltin(Template $t): bool
    {
        return $t->source === 'builtin' && TemplateRegistry::find((string) ($t->builtin_key ?: $t->slug)) !== null;
    }

    /**
     * "Update template" for a built-in: publish the deployed CMS code's copy
     * as its next version. Sites move onto it with "Update sites".
     */
    public function applyBuiltinUpdate(string $id, BuiltinTemplateUpdates $updates): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $t = Template::findOrFail($id);
        abort_unless(self::isRepoBuiltin($t), 422);
        $before = $t->latest_version_id;
        $version = $updates->apply($t);
        $this->dispatch('toast', level: 'success', title: $t->name.' updated',
            message: $version->id === $before
                ? 'Already up to date with the deployed code (version '.$version->version.').'
                : 'Version '.$version->version.' is published. Use “Update sites” to move sites using it onto this version.');
    }

    public static function canNewVersion(Template $t): bool
    {
        return $t->source === 'upload' && $t->status !== 'private'
            && str_starts_with((string) $t->builtin_key, TemplatePaths::UPLOAD_PREFIX);
    }

    /** Add drawer, "new version of {template}" mode: zip or GitHub only. */
    public function openNewVersion(string $id): void
    {
        $t = Template::findOrFail($id);
        if (! self::canNewVersion($t)) {
            $this->dispatch('toast', level: 'error', title: 'Not possible', message: 'Only templates added from a zip or GitHub can get a new version here.');

            return;
        }
        $this->openUpload($t->source_repo ? 'github' : 'zip');
        $this->replacingId = $t->id;
        $this->repoUrl = (string) $t->source_repo;
        $this->repoBranch = (string) $t->source_branch;
    }

    /** "https://github.com/owner/repo" — the one canonical form we store. */
    public static function cleanRepo(string $url): string
    {
        ['owner' => $owner, 'repo' => $repo] = GithubTemplateFetcher::parse($url);

        return "https://github.com/{$owner}/{$repo}";
    }

    /**
     * Push to the repo, press this: the template's saved GitHub repo/branch is
     * downloaded and built as its next version in the background (the admin is
     * told when it's ready; sites move over with "Update sites").
     */
    public function updateFromGithub(string $id, GithubTemplateFetcher $github): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $t = Template::findOrFail($id);
        if (! self::canNewVersion($t) || ! $t->source_repo) {
            $this->dispatch('toast', level: 'error', title: 'No repository', message: 'Add the GitHub repository in Edit first.');

            return;
        }
        if (TemplateUpload::where('key', $t->builtin_key)->whereIn('status', [TemplateUpload::QUEUED, TemplateUpload::SCANNING, TemplateUpload::BUILDING])->exists()) {
            $this->dispatch('toast', level: 'error', title: 'Already updating', message: 'A version of '.$t->name.' is still being built.');

            return;
        }
        try {
            $github->check($t->source_repo, $t->source_branch);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', level: 'error', title: 'Can\'t reach the repository', message: $e->getMessage());

            return;
        }

        $upload = TemplateUpload::create([
            'user_id' => Auth::id(),
            'for_store' => true,
            'visibility' => $t->visibility,
            'key' => $t->builtin_key,
            'replaces_template_id' => $t->id,
            'name' => $t->name,
            'original_filename' => Str::limit($t->source_repo.($t->source_branch ? '#'.$t->source_branch : ''), 180, ''),
            'repo_url' => $t->source_repo,
            'repo_branch' => $t->source_branch,
            'status' => TemplateUpload::QUEUED,
            'step' => 'Downloading from GitHub',
        ]);
        ImportGithubTemplate::dispatch($upload->id, $t->source_repo, $t->source_branch);

        $this->dispatch('toast', level: 'success', title: 'Updating '.$t->name,
            message: 'Pulling the latest from '.Str::after($t->source_repo, 'github.com/').($t->source_branch ? ' ('.$t->source_branch.')' : '').' and building a new version. We\'ll let you know when it\'s ready.');
    }

    /** The template a new-version upload replaces, refusing while another build of it runs. */
    private function replacing(): ?Template
    {
        if (! $this->replacingId) {
            return null;
        }
        $t = Template::findOrFail($this->replacingId);
        abort_unless(self::canNewVersion($t), 422);
        if (TemplateUpload::where('key', $t->builtin_key)->whereIn('status', [TemplateUpload::QUEUED, TemplateUpload::SCANNING, TemplateUpload::BUILDING])->exists()) {
            $this->addError($this->addMode === 'github' ? 'repoUrl' : 'appZip', 'A version of this template is still being built. Wait for it to finish.');

            return null;
        }

        return $t;
    }

    public function openUpload(string $mode = 'zip'): void
    {
        $this->reset('appZip', 'uploadName', 'repoUrl', 'repoBranch', 'siteQuery', 'fromSiteId', 'fromSiteName', 'newVisibility', 'replacingId', 'zipMatchId', 'asSeparate');
        $this->addMode = in_array($mode, ['zip', 'github', 'site'], true) ? $mode : 'zip';
        $this->resetErrorBag();
        $this->uploading = true;
    }

    public function closeUpload(): void
    {
        $this->uploading = false;
    }

    /** Upload a Nuxt app as a new Olux Studio store template (arrives as a draft). */
    /** Picking a zip: does it look like a template we already have? */
    public function updatedAppZip(): void
    {
        $this->zipMatchId = null;
        $this->asSeparate = false;
        if (! $this->replacingId && $this->appZip && method_exists($this->appZip, 'getClientOriginalName')) {
            $this->zipMatchId = self::zipMatch((string) $this->appZip->getClientOriginalName())?->id;
        }
    }

    /**
     * The store template a zip most likely updates: one built from the repo the
     * zip is named after ("template-portfoilo.zip", or GitHub's
     * "template-portfoilo-main.zip"), else the latest one uploaded under the
     * same file name. Only templates that can take a new version.
     */
    public static function zipMatch(string $filename): ?Template
    {
        $base = Str::lower(preg_replace('/\s*\(\d+\)$/', '', pathinfo($filename, PATHINFO_FILENAME)));
        if ($base === '') {
            return null;
        }
        $versionable = fn ($q) => $q->where('source', 'upload')->whereNotNull('creator_id')
            ->where('builtin_key', 'like', TemplatePaths::UPLOAD_PREFIX.'%')->where('status', '!=', 'private');

        $byRepo = Template::where($versionable)->whereNotNull('source_repo')->get()
            ->filter(function (Template $t) use ($base) {
                $repo = Str::lower(Str::afterLast($t->source_repo, '/'));

                return $base === $repo || str_starts_with($base, $repo.'-');
            })
            ->sortByDesc(fn (Template $t) => ($t->status === 'published' ? '1' : '0').$t->updated_at?->format('YmdHis'))->first();
        if ($byRepo) {
            return $byRepo;
        }

        $ids = TemplateUpload::where('for_store', true)->where('original_filename', $filename)->whereNotNull('template_id')->pluck('template_id');

        return Template::where($versionable)->whereIn('id', $ids)
            ->orderByRaw("status = 'published' desc")->latest('updated_at')->first();
    }

    public function uploadTemplate(): void
    {
        $this->validate([
            'appZip' => ['required', 'file', 'mimes:zip', 'max:'.(int) config('templates.uploads.max_zip_kb', 61440)],
            'uploadName' => ['nullable', 'string', 'max:80'],
            'newVisibility' => ['required', 'in:public,private'],
        ], [
            'appZip.required' => 'Choose a .zip of the Nuxt app first.',
            'appZip.mimes' => 'Upload a .zip file.',
            'appZip.max' => 'The zip is too large (60 MB max). Leave out node_modules, .nuxt and .output.',
        ]);
        // Same app again (e.g. re-uploading after a push)? Make it the next version, not a duplicate.
        if (! $this->replacingId && ! $this->asSeparate && ($match = self::zipMatch((string) $this->appZip->getClientOriginalName()))) {
            $this->replacingId = $match->id;
        }
        $replacing = $this->replacing();
        if ($this->replacingId && ! $replacing) {
            return;
        }

        $upload = TemplateUpload::create([
            'user_id' => Auth::id(),
            'for_store' => true,
            'visibility' => $replacing ? $replacing->visibility : $this->newVisibility,
            'key' => $replacing ? $replacing->builtin_key : TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10)),
            'replaces_template_id' => $replacing?->id,
            'name' => $replacing ? $replacing->name : (trim($this->uploadName) ?: null),
            'original_filename' => Str::limit($this->appZip->getClientOriginalName(), 180, ''),
            'status' => TemplateUpload::QUEUED,
            'step' => 'Waiting to start',
        ]);
        File::ensureDirectoryExists(dirname($upload->zipPath()));
        File::copy($this->appZip->getRealPath(), $upload->zipPath());
        ProcessTemplateUpload::dispatch($upload->id);

        $this->uploading = false;
        $this->reset('appZip', 'uploadName', 'replacingId', 'zipMatchId', 'asSeparate');
        $this->setTab('uploads');
        $this->dispatch('toast', level: 'success', title: 'Upload received', message: $replacing
            ? 'It\'s being checked and built as a new version of '.$replacing->name.'. Sites using it can then be updated from its details.'
            : 'It\'s being checked and built in the background. We\'ll let you know when it\'s in the catalog as a draft.');
    }

    /** Import a template app from a GitHub repo — same checked pipeline as a zip upload. */
    public function importFromGithub(GithubTemplateFetcher $github): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $this->validate([
            'repoUrl' => ['required', 'string', 'max:300'],
            'repoBranch' => ['nullable', 'string', 'max:100'],
            'uploadName' => ['nullable', 'string', 'max:80'],
            'newVisibility' => ['required', 'in:public,private'],
        ], ['repoUrl.required' => 'Paste the repository address.']);
        try {
            ['repo' => $repo] = $github::parse($this->repoUrl);
            // Quick reachability check so a wrong address / token / branch shows here at once;
            // the download itself runs in the background.
            $github->check($this->repoUrl, $this->repoBranch);
        } catch (\RuntimeException $e) {
            $this->addError('repoUrl', $e->getMessage());

            return;
        }

        $replacing = $this->replacing();
        if ($this->replacingId && ! $replacing) {
            return;
        }

        $upload = TemplateUpload::create([
            'user_id' => Auth::id(),
            'for_store' => true,
            'visibility' => $replacing ? $replacing->visibility : $this->newVisibility,
            'key' => $replacing ? $replacing->builtin_key : TemplatePaths::UPLOAD_PREFIX.Str::lower(Str::random(10)),
            'replaces_template_id' => $replacing?->id,
            'name' => $replacing ? $replacing->name : (trim($this->uploadName) ?: Str::headline($repo)),
            'original_filename' => Str::limit(trim($this->repoUrl).($this->repoBranch ? '#'.$this->repoBranch : ''), 180, ''),
            'repo_url' => self::cleanRepo($this->repoUrl),
            'repo_branch' => trim($this->repoBranch) ?: null,
            'status' => TemplateUpload::QUEUED,
            'step' => 'Downloading from GitHub',
        ]);
        ImportGithubTemplate::dispatch($upload->id, trim($this->repoUrl), trim($this->repoBranch) ?: null);

        $this->uploading = false;
        $this->reset('replacingId');
        $this->setTab('uploads');
        $this->dispatch('toast', level: 'success', title: 'Import started', message: ($replacing
            ? 'Downloading and building a new version of '.$replacing->name.' in the background.'
            : 'Downloading and building it in the background.').' You can leave this page — we\'ll let you know when it\'s ready.');
    }

    /** Sites matching the search box, for "Copy an existing site". */
    public function getSiteMatchesProperty()
    {
        $q = trim($this->siteQuery);
        if (mb_strlen($q) < 2) {
            return collect();
        }

        return Site::with('user:id,name,email')
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('domain', 'like', "%{$q}%"))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'domain', 'template', 'user_id']);
    }

    public function pickSite(string $id): void
    {
        $site = Site::findOrFail($id);
        $this->fromSiteId = $site->id;
        $this->fromSiteName = $this->fromSiteName ?: Str::headline($site->name).' template';
    }

    /** Turn an existing site into a new draft template (keeps its renderer). */
    public function createFromSite(SiteToTemplate $maker): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        abort_if($this->replacingId !== null, 422);
        $this->validate([
            'fromSiteId' => ['required'],
            'fromSiteName' => ['required', 'string', 'max:80'],
            'newVisibility' => ['required', 'in:public,private'],
        ], ['fromSiteId.required' => 'Pick a site first.', 'fromSiteName.required' => 'Name the new template.']);
        $site = Site::findOrFail($this->fromSiteId);
        if ($site->livePages()->count() === 0) {
            $this->addError('fromSiteId', 'That site has no pages to copy yet.');

            return;
        }
        $t = $maker->create($site, Auth::user(), trim($this->fromSiteName), $this->newVisibility);

        $this->uploading = false;
        $this->setTab('catalog');
        $this->startEdit($t->id);
        $this->dispatch('toast', level: 'success', title: 'Template created',
            message: $t->name.' is a draft with '.$site->livePages()->count().' pages. Add its details, then publish it.');
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

    /** Hide a template everywhere (store, libraries, new installs). Sites using it keep their design. */
    public function hide(string $id): void
    {
        $t = Template::findOrFail($id);
        if ($t->status === 'in_review') {
            $this->dispatch('toast', level: 'error', title: 'In review', message: 'Approve or send it back first.');

            return;
        }
        if ($t->status === 'archived') {
            return;
        }
        $t->update(['status' => 'archived', 'status_before_hide' => $t->status]);
        Cache::forget('template_catalog_categories');
        $this->dispatch('toast', level: 'success', title: 'Hidden',
            message: $t->name.' is hidden and can\'t be applied to new sites. Sites already using it keep it. Show it again any time.');
    }

    /** Un-hide: back to exactly what it was before (published, draft, private…). */
    public function show(string $id): void
    {
        $t = Template::where('status', 'archived')->findOrFail($id);
        $back = in_array($t->status_before_hide, ['published', 'draft', 'private', 'rejected'], true) ? $t->status_before_hide : 'draft';
        $t->update(['status' => $back, 'status_before_hide' => null]);
        Cache::forget('template_catalog_categories');
        $this->dispatch('toast', level: 'success', title: 'Showing again',
            message: $back === 'published' ? $t->name.' is back in the store.' : $t->name.' is back as '.($back === 'draft' ? 'a draft — publish it when ready.' : $back.'.'));
    }

    /** Why a template can't be deleted (null = it can). Hiding is always the safe alternative. */
    public static function deleteBlocker(Template $t): ?string
    {
        $sites = SiteTemplate::where('template_id', $t->id)->whereNotNull('applied_at')->whereHas('site')->count();

        return match (true) {
            $sites > 0 => $sites.' '.Str::plural('site', $sites).($sites === 1 ? ' uses' : ' use').' this template — hide it instead.',
            // The catalog row of an app shipped in the repo: templates:sync would recreate it.
            $t->source === 'builtin' && $t->slug === $t->builtin_key && TemplateRegistry::find((string) $t->builtin_key) !== null => 'Built-in templates come back on the next sync — hide it instead.',
            TemplateUpload::where(fn ($q) => $q->where('template_id', $t->id)->orWhere('replaces_template_id', $t->id))
                ->whereIn('status', [TemplateUpload::QUEUED, TemplateUpload::SCANNING, TemplateUpload::BUILDING])->exists() => 'A version is still being built — wait for it to finish.',
            default => null,
        };
    }

    /**
     * Delete a template no live site uses (e.g. a duplicate import): its
     * versions, saved copies in libraries, upload records and — when no other
     * template uses the same app — its files. Sales stay on record by name.
     */
    public function deleteTemplate(string $id): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $t = Template::findOrFail($id);
        if ($blocker = self::deleteBlocker($t)) {
            $this->dispatch('toast', level: 'error', title: 'Can\'t delete', message: $blocker);

            return;
        }
        $key = (string) $t->builtin_key;
        $sharedApp = $key !== '' && Template::where('builtin_key', $key)->whereKeyNot($t->id)->exists();

        DB::transaction(function () use ($t) {
            TemplatePurchase::where('template_id', $t->id)->whereNull('template_name')->update(['template_name' => $t->name]);
            SiteTemplate::where('template_id', $t->id)->delete(); // unapplied library copies only (applied ones block above)
            TemplateEntitlement::where('template_id', $t->id)->delete();
            foreach (TemplateUpload::where('template_id', $t->id)->orWhere('replaces_template_id', $t->id)->get() as $u) {
                File::delete($u->zipPath());
                $u->delete();
            }
            $t->update(['latest_version_id' => null]);
            $t->versions()->delete();
            $t->delete();
        });
        $this->replaceThumbnailFiles($t);
        if (TemplatePaths::isUpload($key) && ! $sharedApp) {
            File::deleteDirectory(TemplatePaths::uploadsRoot().'/'.$key);
            File::deleteDirectory(TemplatePaths::shellDir($key));
        }
        if ($this->openId === $id) {
            $this->openId = null;
        }
        if ($this->editingId === $id) {
            $this->editingId = null;
        }
        Cache::forget('template_catalog_categories');
        AccountActivity::record(Auth::id(), 'template.deleted', 'Deleted template "'.$t->name.'"',
            ['actor_id' => Auth::id(), 'category' => 'templates', 'icon' => 'template', 'meta' => ['template_id' => $t->id, 'key' => $key ?: null]]);
        $this->dispatch('toast', level: 'success', title: 'Template deleted', message: $t->name.' was removed'.($sharedApp ? ' (its app is kept — another template uses it).' : ' with its files.'));
    }

    /** Remove an uploaded thumbnail file (catalog row already gone). */
    private function replaceThumbnailFiles(Template $t): void
    {
        $prefix = Storage::disk('public')->url('template-thumbnails/uploaded/');
        if ($t->thumbnail_url && str_starts_with($t->thumbnail_url, $prefix)) {
            Storage::disk('public')->delete('template-thumbnails/uploaded/'.Str::after($t->thumbnail_url, $prefix));
        }
    }

    /** Move every site using the template onto its latest version and re-run their install. */
    public function updateSites(string $id): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $t = Template::findOrFail($id);
        $rows = SiteTemplate::where('template_id', $t->id)->whereNotNull('applied_at')->whereHas('site')
            ->where(fn ($q) => $q->whereNull('template_version_id')->orWhere('template_version_id', '!=', $t->latest_version_id))
            ->get();
        foreach ($rows as $row) {
            $row->update(['template_version_id' => $t->latest_version_id]);
            $row->site?->setAttr('template_install', 'installing');
            $row->site?->setAttr(InstallProgress::ATTR, json_encode(['percent' => 1, 'label' => 'Getting started', 'step' => 'start', 'done' => 0, 'total' => 0]));
            InstallTemplateJob::dispatch($row->site_id, $row->id, refresh: true);
        }
        $this->dispatch('toast', level: 'success', title: 'Updating sites',
            message: $rows->count().' '.Str::plural('site', $rows->count()).' moving to the latest version of '.$t->name.'. Their content is kept.');
    }

    /** Remove a failed upload (one that never became a template) with its files. */
    public function deleteUpload(string $id): void
    {
        $u = TemplateUpload::findOrFail($id);
        if ($u->inProgress()) {
            return;
        }
        if ($u->replaces_template_id) {
            // A new-version upload shares its template's key and files — only the record goes.
            File::delete($u->zipPath());
            $u->delete();
            $this->dispatch('toast', level: 'success', title: 'Upload deleted', message: 'The template and its versions are unchanged.');

            return;
        }
        if ($u->template_id) {
            // Templates are never deleted from here — hide them instead.
            $this->dispatch('toast', level: 'error', title: 'It\'s a template now', message: 'This upload became a template. Hide the template instead.');

            return;
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
        $usedBy = SiteTemplate::whereNotNull('applied_at')->whereNotNull('template_id')->whereHas('site')
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
            'sites' => SiteTemplate::with('site.user')->where('template_id', $opened->id)->whereNotNull('applied_at')->whereHas('site')
                ->latest('applied_at')->limit(25)->get(),
            'owners' => TemplateEntitlement::where('template_id', $opened->id)->count(),
            'purchases' => TemplatePurchase::where('template_id', $opened->id)->where('status', 'paid')
                ->selectRaw('count(*) as n, coalesce(sum(price_cents),0) as gross, coalesce(sum(platform_fee_cents),0) as fees')->first(),
            'upload' => TemplateUpload::where('template_id', $opened->id)->latest()->first(),
            'built' => TemplatePaths::hasShell((string) ($opened->builtin_key ?: $opened->slug)),
            'outdated' => SiteTemplate::where('template_id', $opened->id)->whereNotNull('applied_at')->whereHas('site')
                ->where(fn ($q) => $q->whereNull('template_version_id')->orWhere('template_version_id', '!=', $opened->latest_version_id))->count(),
            'delete_blocker' => self::deleteBlocker($opened),
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
            'replacingTemplate' => $this->replacingId ? Template::find($this->replacingId) : null,
            'editingTemplate' => $this->editingId ? Template::find($this->editingId) : null,
        ]);
    }
}
