<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Media;
use App\Models\Site;
use App\Models\Subscription;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Newsletter;
use App\Modules\Newsletter\Services\CampaignRenderer;
use App\Modules\Newsletter\Services\CampaignSender;
use App\Modules\Newsletter\Services\SendQuota;
use App\Modules\Newsletter\Services\SubscriberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Newsletter admin (/{site}/newsletter): subscribers + campaigns on the
 * house tri-layout. Viewing needs newsletter.view (route); every change
 * needs newsletter.manage.
 */
class NewsletterPage extends Component
{
    use WithFileUploads;
    use WithLayoutMode;
    use WithPagination;

    public const PER_PAGE = 24;

    public Site $site;

    #[Url(except: 'subscribers')]
    public string $tab = 'subscribers';

    public string $search = '';

    /** Subscriber status filter: all | pending | subscribed | unsubscribed | bounced */
    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $tag = '';

    /** Campaign filter: all | draft | scheduled | sending | sent */
    public string $campaignFilter = 'all';

    // ── Add one subscriber ──
    public bool $showAdd = false;

    public string $addEmail = '';

    public string $addName = '';

    public string $addTags = '';

    // ── CSV import ──
    public bool $showImport = false;

    public $importFile = null;

    public string $importTags = '';

    public ?array $importResult = null;

    // ── Campaign editor (its own panel) ──
    public bool $editing = false;

    public ?string $campaignId = null;

    public string $subject = '';

    public string $preheader = '';

    public string $body = '';

    public string $audienceTag = '';

    public string $scheduleAt = '';

    public string $testEmail = '';

    public bool $previewing = false;

    /** Bumped whenever another campaign opens, so the (wire:ignore) editor re-mounts. */
    public int $editorKey = 0;

    /** Plan-limit refusal (rendered with an upgrade link). */
    public ?string $limitError = null;

    public ?string $notice = null;

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'newsletter.view'), 403);
        $this->site = $site;
        $this->initLayout('newsletter', 'grid');
        $this->tab = in_array($this->tab, ['subscribers', 'campaigns'], true) ? $this->tab : 'subscribers';
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    public function getCanManageProperty(): bool
    {
        return $this->site->allows(Auth::user(), 'newsletter.manage');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'newsletter.manage'), 403);
    }

    private function subscribers(): SubscriberService
    {
        return app(SubscriberService::class);
    }

    private function findCampaign(string $id): NewsletterCampaign
    {
        return NewsletterCampaign::where('site_id', $this->site->id)->findOrFail($id);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['subscribers', 'campaigns'], true) ? $tab : 'subscribers';
        $this->search = '';
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, Subscription::STATUSES, true) ? $status : 'all';
        $this->resetPage();
    }

    public function setTag(string $tag): void
    {
        $this->tag = $this->tag === $tag ? '' : mb_substr($tag, 0, 40);
        $this->resetPage();
    }

    public function setCampaignFilter(string $filter): void
    {
        $this->campaignFilter = in_array($filter, ['draft', 'scheduled', 'sending', 'sent'], true) ? $filter : 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ── Subscribers ─────────────────────────────────────────────────────

    public function addSubscriber(): void
    {
        $this->authorizeManage();
        $this->validate([
            'addEmail' => ['required', 'email', 'max:255'],
            'addName' => ['nullable', 'string', 'max:255'],
            'addTags' => ['nullable', 'string', 'max:300'],
        ]);

        // Added by the owner (they vouch for consent): subscribed straight away.
        [, $outcome] = $this->subscribers()->subscribe($this->site, $this->addEmail, $this->addName ?: null, 'admin', null,
            Subscription::cleanTags($this->addTags), skipConfirm: true);

        $this->notice = $outcome === 'already' ? $this->addEmail.' is already subscribed.' : $this->addEmail.' added.';
        $this->reset(['addEmail', 'addName', 'addTags', 'showAdd']);
    }

    public function importCsv(): void
    {
        $this->authorizeManage();
        $this->validate([
            'importFile' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
            'importTags' => ['nullable', 'string', 'max:300'],
        ]);

        $this->importResult = $this->subscribers()->import($this->site, (string) file_get_contents($this->importFile->getRealPath()),
            Subscription::cleanTags($this->importTags));
        $this->reset(['importFile', 'importTags']);
    }

    public function exportCsv()
    {
        $csv = $this->subscribers()->csv($this->subscribers()->query($this->site, $this->search, $this->status, $this->tag));

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $this->site->name.'-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function unsubscribeSubscriber(string $id): void
    {
        $this->authorizeManage();
        $sub = Subscription::where('site_id', $this->site->id)->findOrFail($id);
        $this->subscribers()->unsubscribe($sub);
    }

    public function resendConfirmation(string $id): void
    {
        $this->authorizeManage();
        $sub = Subscription::where('site_id', $this->site->id)->findOrFail($id);
        if ($sub->isPending()) {
            $this->subscribers()->sendConfirmation($sub);
            $this->notice = 'Confirmation email sent again to '.$sub->email.'.';
        }
    }

    public function deleteSubscriber(string $id): void
    {
        $this->authorizeManage();
        Subscription::where('site_id', $this->site->id)->whereKey($id)->delete();
    }

    // ── Campaigns ───────────────────────────────────────────────────────

    public function newCampaign(): void
    {
        $this->authorizeManage();
        $this->resetEditor();
        $this->tab = 'campaigns';
        $this->editing = true;
        $this->editorKey++;
    }

    public function editCampaign(string $id): void
    {
        $this->authorizeManage();
        $c = $this->findCampaign($id);
        $this->resetEditor();
        $this->campaignId = $c->id;
        $this->subject = $c->subject;
        $this->preheader = (string) $c->preheader;
        // Library images are stored as @media refs; the editor needs real URLs.
        $this->body = Media::resolveHtml($this->site->id, $c->body);
        $this->audienceTag = (string) $c->audience_tag;
        $this->scheduleAt = $c->scheduled_at?->format('Y-m-d\TH:i') ?? '';
        $this->editing = true;
        $this->editorKey++;
        $this->tab = 'campaigns';
    }

    public function closeEditor(): void
    {
        $this->resetEditor();
    }

    private function resetEditor(): void
    {
        $this->reset(['editing', 'campaignId', 'subject', 'preheader', 'body', 'audienceTag', 'scheduleAt', 'previewing', 'limitError']);
        $this->testEmail = (string) Auth::user()?->email;
        $this->resetValidation();
    }

    public function togglePreview(): void
    {
        $this->previewing = ! $this->previewing;
    }

    /** Validate + store the editor's campaign (draft unless already scheduled). */
    private function persist(): NewsletterCampaign
    {
        $this->authorizeManage();
        $this->validate([
            'subject' => ['required', 'string', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:200000'],
            'audienceTag' => ['nullable', 'string', 'max:60'],
        ]);

        $data = [
            'subject' => trim($this->subject),
            'preheader' => trim($this->preheader) ?: null,
            'body' => $this->body !== '' ? Media::refHtml($this->site->id, $this->body) : null,
            'audience_tag' => trim($this->audienceTag) ?: null,
        ];

        if ($this->campaignId) {
            $c = $this->findCampaign($this->campaignId);
            abort_unless($c->isEditable(), 422, 'A sent campaign can\'t be edited.');
            $c->update($data);
        } else {
            $c = NewsletterCampaign::create($data + ['site_id' => $this->site->id, 'status' => NewsletterCampaign::DRAFT, 'created_by' => Auth::id()]);
            $this->campaignId = $c->id;
        }

        return $c;
    }

    public function saveDraft(): void
    {
        $c = $this->persist();
        if ($c->status === NewsletterCampaign::SCHEDULED) {
            $this->notice = 'Saved — still scheduled for '.$c->scheduled_at->format('j M, H:i').'.';
        } else {
            $this->notice = 'Draft saved.';
        }
    }

    public function sendTest(): void
    {
        $this->validate(['testEmail' => ['required', 'email', 'max:255']]);
        $c = $this->persist();
        app(CampaignSender::class)->sendTest($c, $this->testEmail);
        $this->notice = 'Test sent to '.$this->testEmail.'.';
    }

    public function sendNow(): void
    {
        $c = $this->persist();
        $this->limitError = null;

        if ($error = app(CampaignSender::class)->start($c)) {
            $this->reportStartError($error);

            return;
        }
        $this->notice = 'Sending “'.$c->subject.'” to '.number_format($c->fresh()->recipients_count).' subscribers.';
        $this->resetEditor();
    }

    public function schedule(): void
    {
        $this->validate(['scheduleAt' => ['required', 'date', 'after:now']], ['scheduleAt.after' => 'Pick a time in the future.']);
        $c = $this->persist();
        $this->limitError = null;

        $count = $c->audience()->count();
        if ($count === 0) {
            $this->addError('scheduleAt', 'There is nobody in this audience to send to yet.');

            return;
        }
        if ($error = SendQuota::check($this->site, $count)) {
            $this->limitError = $error;

            return;
        }

        $at = Carbon::parse($this->scheduleAt);
        $c->update(['status' => NewsletterCampaign::SCHEDULED, 'scheduled_at' => $at, 'error' => null]);
        $this->notice = '“'.$c->subject.'” is scheduled for '.$at->format('j M Y, H:i').'.';
        $this->resetEditor();
    }

    public function unschedule(string $id): void
    {
        $this->authorizeManage();
        $c = $this->findCampaign($id);
        if ($c->status === NewsletterCampaign::SCHEDULED) {
            $c->update(['status' => NewsletterCampaign::DRAFT, 'scheduled_at' => null]);
        }
    }

    public function duplicateCampaign(string $id): void
    {
        $this->authorizeManage();
        $c = $this->findCampaign($id);
        NewsletterCampaign::create([
            'site_id' => $this->site->id, 'subject' => mb_substr($c->subject.' (copy)', 0, 190), 'preheader' => $c->preheader,
            'body' => $c->body, 'audience_tag' => $c->audience_tag, 'status' => NewsletterCampaign::DRAFT, 'created_by' => Auth::id(),
        ]);
    }

    public function deleteCampaign(string $id): void
    {
        $this->authorizeManage();
        $c = $this->findCampaign($id);
        abort_if($c->status === NewsletterCampaign::SENDING, 422, 'A campaign can\'t be deleted while it is sending.');
        $c->delete();
        if ($this->campaignId === $id) {
            $this->resetEditor();
        }
    }

    private function reportStartError(string $error): void
    {
        if (str_contains($error, 'Upgrade your plan')) {
            $this->limitError = $error;
        } else {
            $this->addError('send', $error);
        }
    }

    // ── Data ────────────────────────────────────────────────────────────

    public function getMediaAssetsProperty(): array
    {
        return $this->site->media()
            ->whereIn('file_type', ['image'])
            ->latest()->limit(120)
            ->get(['id', 'name', 'file_type', 'url'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->file_type, 'url' => $m->publicUrl()])
            ->all();
    }

    /** Rendered email for the editor's preview pane (current, unsaved content). */
    public function getPreviewHtmlProperty(): string
    {
        $draft = new NewsletterCampaign(['site_id' => $this->site->id, 'body' => Media::refHtml($this->site->id, $this->body)]);
        $draft->site_id = $this->site->id;

        return view('emails.newsletter.campaign', [
            'brand' => Newsletter::brand($this->site),
            'preheader' => $this->preheader,
            'body' => app(CampaignRenderer::class)->baseHtml($draft),
            'unsubscribeUrl' => '#',
            'isTest' => false,
        ])->render();
    }

    private function stats(): array
    {
        $siteId = $this->site->id;
        $byStatus = Subscription::where('site_id', $siteId)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $month = now()->startOfMonth();

        $growth = Subscription::where('site_id', $siteId)
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_month, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as new_last, SUM(CASE WHEN unsubscribed_at >= ? THEN 1 ELSE 0 END) as unsub_month',
                [$month, $month->copy()->subMonth(), $month, $month])
            ->first();

        $c = NewsletterCampaign::where('site_id', $siteId)
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_campaigns,
                SUM(CASE WHEN status = 'sent' THEN sent_count ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'sent' THEN opens_count ELSE 0 END) as opens,
                SUM(CASE WHEN status = 'sent' THEN clicks_count ELSE 0 END) as clicks,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) as sending,
                SUM(failed_count) as failed,
                COUNT(*) as total")
            ->first();

        $delivered = (int) $c->delivered;

        return [
            'subscribed' => $byStatus[Subscription::SUBSCRIBED] ?? 0,
            'pending' => $byStatus[Subscription::PENDING] ?? 0,
            'unsubscribed' => $byStatus[Subscription::UNSUBSCRIBED] ?? 0,
            'bounced' => $byStatus[Subscription::BOUNCED] ?? 0,
            'total' => (int) $byStatus->sum(),
            'newMonth' => (int) $growth->new_month,
            'newLastMonth' => (int) $growth->new_last,
            'unsubMonth' => (int) $growth->unsub_month,
            'sentCampaigns' => (int) $c->sent_campaigns,
            'campaigns' => (int) $c->total,
            'delivered' => $delivered,
            'openRate' => $delivered > 0 ? (int) round($c->opens / $delivered * 100) : null,
            'clickRate' => $delivered > 0 ? (int) round($c->clicks / $delivered * 100) : null,
            'drafts' => (int) $c->drafts,
            'scheduled' => (int) $c->scheduled,
            'sending' => (int) $c->sending,
            'failed' => (int) $c->failed,
        ];
    }

    public function render()
    {
        $service = $this->subscribers();
        $tags = $service->tags($this->site);

        $subscriberList = $this->tab === 'subscribers'
            ? $service->query($this->site, $this->search, $this->status, $this->tag)->latest()->paginate(self::PER_PAGE)
            : null;

        $allCampaigns = NewsletterCampaign::where('site_id', $this->site->id)
            ->select(['id', 'site_id', 'subject', 'preheader', 'audience_tag', 'status', 'scheduled_at', 'started_at', 'sent_at',
                'recipients_count', 'sent_count', 'failed_count', 'opens_count', 'clicks_count', 'unsubscribes_count', 'error', 'created_at', 'updated_at'])
            ->latest('updated_at')->limit(200)->get();
        $needle = mb_strtolower(trim($this->search));
        $campaigns = $allCampaigns
            ->when($this->campaignFilter !== 'all', fn ($c) => $c->where('status', $this->campaignFilter))
            ->when($this->tab === 'campaigns' && $needle !== '', fn ($c) => $c->filter(fn ($x) => str_contains(mb_strtolower($x->subject), $needle)))
            ->values();

        $audienceCount = null;
        if ($this->editing) {
            $audienceCount = Subscription::where('site_id', $this->site->id)->active()
                ->when(trim($this->audienceTag) !== '', fn ($q) => $q->tagged(trim($this->audienceTag)))->count();
        }

        return view('livewire.newsletter-page', [
            'stats' => $this->stats(),
            'quota' => SendQuota::forSite($this->site),
            'tags' => $tags,
            'subscriberList' => $subscriberList,
            'campaigns' => $campaigns,
            'campaignCounts' => $allCampaigns->countBy('status')->all() + ['all' => $allCampaigns->count()],
            'recentCampaigns' => $allCampaigns->take(4),
            'failedCampaigns' => $allCampaigns->where('failed_count', '>', 0)->take(3),
            'erroredCampaigns' => $allCampaigns->whereNotNull('error')->where('status', NewsletterCampaign::DRAFT)->take(3),
            'audienceCount' => $audienceCount,
            'canManage' => $this->canManage,
            'editingCampaign' => $this->campaignId ? $allCampaigns->firstWhere('id', $this->campaignId) : null,
        ]);
    }
}
