<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Contact;
use App\Models\Site;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\Models\ReviewRequest;
use App\Modules\Reviews\ReviewService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reviews & Testimonials admin — /{siteID}/reviews.
 * Tabs: Reviews (moderate, feature, reply, add by hand) and Requests (ask
 * customers for a review by email). Viewing needs reviews.view (route);
 * every change needs reviews.manage.
 */
class ReviewsPage extends Component
{
    use WithLayoutMode;

    public Site $site;

    #[Url(except: 'reviews')]
    public string $tab = 'reviews';

    public string $search = '';

    /** all | pending | published | hidden | featured */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** 0 = any rating, else exactly N stars. */
    public int $stars = 0;

    /** newest | oldest | highest | lowest */
    public string $sort = 'newest';

    public int $limit = 24;

    public ?string $replyingId = null;

    public string $replyBody = '';

    // ── Add a review by hand ──
    public bool $showAdd = false;

    public array $m = ['name' => '', 'email' => '', 'rating' => 5, 'title' => '', 'body' => '', 'photo' => '', 'date' => ''];

    // ── Requests ──
    public string $rqName = '';

    public string $rqEmail = '';

    public string $rqPaste = '';

    public string $contactSearch = '';

    public array $pickedContacts = [];

    public int $requestsLimit = 30;

    public string $notice = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('reviews', 'grid');
        if (! in_array($this->tab, ['reviews', 'requests'], true)) {
            $this->tab = 'reviews';
        }
        if (! in_array($this->filter, ['all', 'pending', 'published', 'hidden', 'featured'], true)) {
            $this->filter = 'all';
        }
    }

    public function canManage(): bool
    {
        return $this->site->allows(Auth::user(), 'reviews.manage');
    }

    private function guard(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function review(string $id): Review
    {
        return Review::where('site_id', $this->site->id)->findOrFail($id);
    }

    // ── Toolbar ────────────────────────────────────────────────────

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['reviews', 'requests'], true) ? $tab : 'reviews';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'pending', 'published', 'hidden', 'featured'], true) ? $filter : 'all';
        $this->limit = 24;
    }

    public function setStars(int $stars): void
    {
        $this->stars = $this->stars === $stars ? 0 : max(0, min(5, $stars));
    }

    /** Right rail → the pending reviews. */
    public function showPending(): void
    {
        $this->tab = 'reviews';
        $this->search = '';
        $this->stars = 0;
        $this->setFilter('pending');
    }

    /** Right rail → open the reply editor on a low rating, with it in view. */
    public function replyTo(string $id): void
    {
        $r = $this->review($id);
        $this->tab = 'reviews';
        $this->search = '';
        $this->setFilter('all');
        $this->stars = $r->rating;
        $this->sort = 'newest';
        $this->startReply($id);
    }

    public function loadMore(): void
    {
        $this->limit += 24;
    }

    // ── Moderation ─────────────────────────────────────────────────

    public function approve(string $id): void
    {
        $this->guard();
        $r = $this->review($id);
        $r->update(['status' => 'published', 'published_at' => $r->published_at ?? now()]);
    }

    public function hide(string $id): void
    {
        $this->guard();
        $this->review($id)->update(['status' => 'hidden', 'featured' => false]);
    }

    public function toggleFeature(string $id): void
    {
        $this->guard();
        $r = $this->review($id);
        // Featuring a review shows it, so a pending one is published with it.
        $r->featured
            ? $r->update(['featured' => false])
            : $r->update(['featured' => true, 'status' => 'published', 'published_at' => $r->published_at ?? now()]);
    }

    public function startReply(string $id): void
    {
        $this->guard();
        $this->replyingId = $id;
        $this->replyBody = (string) $this->review($id)->reply_body;
        $this->resetErrorBag('replyBody');
    }

    public function cancelReply(): void
    {
        $this->replyingId = null;
        $this->replyBody = '';
    }

    public function saveReply(): void
    {
        $this->guard();
        if (! $this->replyingId) {
            return;
        }
        $this->validate(['replyBody' => ['nullable', 'string', 'max:2000']]);
        $body = ReviewService::clean($this->replyBody, 2000);
        $this->review($this->replyingId)->update(['reply_body' => $body, 'replied_at' => $body ? now() : null]);
        $this->cancelReply();
    }

    public function deleteReview(string $id): void
    {
        $this->guard();
        $this->review($id)->delete();
        if ($this->replyingId === $id) {
            $this->cancelReply();
        }
    }

    // ── Add manually ───────────────────────────────────────────────

    public function openAdd(): void
    {
        $this->guard();
        $this->reset('m');
        $this->resetErrorBag();
        $this->showAdd = true;
    }

    public function cancelAdd(): void
    {
        $this->showAdd = false;
    }

    public function saveManual(): void
    {
        $this->guard();
        $this->validate([
            'm.name' => ['required', 'string', 'max:120'],
            'm.email' => ['nullable', 'email', 'max:190'],
            'm.rating' => ['required', 'integer', 'between:1,5'],
            'm.title' => ['nullable', 'string', 'max:160'],
            'm.body' => ['required', 'string', 'max:3000'],
            'm.photo' => ['nullable', 'string', 'max:500'],
            'm.date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['m.name' => 'name', 'm.body' => 'review', 'm.rating' => 'rating', 'm.email' => 'email', 'm.date' => 'date']);

        $when = filled($this->m['date']) ? Carbon::parse($this->m['date'])->startOfDay() : now();
        $r = new Review([
            'site_id' => $this->site->id,
            'name' => ReviewService::clean($this->m['name'], 120),
            'email' => filled($this->m['email']) ? strtolower(trim($this->m['email'])) : null,
            'rating' => (int) $this->m['rating'],
            'title' => ReviewService::clean($this->m['title'], 160),
            'body' => (string) ReviewService::clean($this->m['body'], 3000),
            'photo' => filled($this->m['photo']) ? trim($this->m['photo']) : null,
            'status' => 'published',
            'source' => 'manual',
            'published_at' => $when,
        ]);
        $r->created_at = $when;
        $r->save();

        $this->showAdd = false;
        $this->reset('m');
    }

    // ── Requests ───────────────────────────────────────────────────

    public function sendOne(): void
    {
        $this->guard();
        $this->validate(['rqEmail' => ['required', 'email', 'max:190'], 'rqName' => ['nullable', 'string', 'max:120']],
            [], ['rqEmail' => 'email', 'rqName' => 'name']);
        $contact = Contact::where('site_id', $this->site->id)->where('email', strtolower(trim($this->rqEmail)))->first();
        ReviewService::sendRequest($this->site, $this->rqEmail, $this->rqName ?: $contact?->name, $contact?->id);
        $this->notice = 'Review request sent to '.trim($this->rqEmail).'.';
        $this->reset('rqEmail', 'rqName');
    }

    /** Send to the picked contacts and/or the pasted addresses (max 200 at once). */
    public function sendBulk(): void
    {
        $this->guard();
        $targets = [];
        $contacts = Contact::where('site_id', $this->site->id)->whereIn('id', array_slice($this->pickedContacts, 0, 200))
            ->whereNotNull('email')->get(['id', 'name', 'email']);
        foreach ($contacts as $c) {
            $targets[strtolower(trim($c->email))] = [$c->name, $c->id];
        }
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $this->rqPaste, $mm);
        foreach ($mm[0] as $email) {
            $targets[strtolower($email)] ??= [null, null];
        }
        if (! $targets) {
            $this->addError('rqPaste', 'Pick some contacts or paste at least one email address.');

            return;
        }

        $sent = 0;
        foreach (array_slice($targets, 0, 200, true) as $email => [$name, $contactId]) {
            if (ReviewService::sendRequest($this->site, $email, $name, $contactId)) {
                $sent++;
            }
        }
        $this->notice = "Review requests sent to {$sent} ".($sent === 1 ? 'person' : 'people').'.';
        $this->reset('rqPaste', 'pickedContacts');
    }

    public function resend(string $id): void
    {
        $this->guard();
        $req = ReviewRequest::where('site_id', $this->site->id)->whereNull('completed_at')->findOrFail($id);
        ReviewService::mailRequest($this->site, $req, false);
        $this->notice = 'Request re-sent to '.$req->email.'.';
    }

    public function deleteRequest(string $id): void
    {
        $this->guard();
        ReviewRequest::where('site_id', $this->site->id)->whereKey($id)->delete();
    }

    public function moreRequests(): void
    {
        $this->requestsLimit += 30;
    }

    // ── Data ───────────────────────────────────────────────────────

    /** Every left-rail / filter count in ONE query. */
    private function stats(): array
    {
        $now = now();
        $row = Review::where('site_id', $this->site->id)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
            SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
            SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) AS featured,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS this_month,
            SUM(CASE WHEN reply_body IS NOT NULL AND status <> 'hidden' THEN 1 ELSE 0 END) AS replied,
            SUM(CASE WHEN status <> 'hidden' THEN 1 ELSE 0 END) AS visible,
            SUM(CASE WHEN status = 'published' AND created_at >= ? THEN rating ELSE 0 END) AS recent_sum,
            SUM(CASE WHEN status = 'published' AND created_at >= ? THEN 1 ELSE 0 END) AS recent_n,
            SUM(CASE WHEN status = 'published' AND created_at >= ? AND created_at < ? THEN rating ELSE 0 END) AS prev_sum,
            SUM(CASE WHEN status = 'published' AND created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS prev_n
        ", [
            $now->copy()->startOfMonth(), $now->copy()->subDays(30), $now->copy()->subDays(30),
            $now->copy()->subDays(60), $now->copy()->subDays(30), $now->copy()->subDays(60), $now->copy()->subDays(30),
        ])->first();

        $req = ReviewRequest::where('site_id', $this->site->id)->selectRaw('
            COUNT(*) AS sent,
            SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
            SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed
        ')->first();

        $i = fn ($v) => (int) ($v ?? 0);

        return [
            'total' => $i($row->total), 'pending' => $i($row->pending), 'published' => $i($row->published),
            'hidden' => $i($row->hidden), 'featured' => $i($row->featured), 'thisMonth' => $i($row->this_month),
            'replied' => $i($row->replied), 'visible' => $i($row->visible),
            'responseRate' => $i($row->visible) ? (int) round($i($row->replied) / $i($row->visible) * 100) : 0,
            'recentAvg' => $i($row->recent_n) ? round($row->recent_sum / $row->recent_n, 1) : null,
            'recentN' => $i($row->recent_n),
            'prevAvg' => $i($row->prev_n) ? round($row->prev_sum / $row->prev_n, 1) : null,
            'reqSent' => $i($req->sent), 'reqOpened' => $i($req->opened), 'reqCompleted' => $i($req->completed),
        ];
    }

    private function reviewsQuery()
    {
        $q = Review::where('site_id', $this->site->id);
        match ($this->filter) {
            'pending', 'published', 'hidden' => $q->where('status', $this->filter),
            'featured' => $q->where('featured', true),
            default => null,
        };
        if ($this->stars) {
            $q->where('rating', $this->stars);
        }
        if (($s = trim($this->search)) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhere('body', 'like', $like)->orWhere('email', 'like', $like));
        }
        match ($this->sort) {
            'oldest' => $q->orderBy('created_at'),
            'highest' => $q->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest' => $q->orderBy('rating')->orderByDesc('created_at'),
            default => $q->orderByDesc('created_at'),
        };

        return $q;
    }

    public function render()
    {
        $stats = $this->stats();
        $aggregate = ReviewService::aggregate($this->site);

        $reviews = $this->reviewsQuery()->limit($this->limit + 1)->get();
        $hasMore = $reviews->count() > $this->limit;
        $reviews = $reviews->take($this->limit);

        $pendingList = Review::where('site_id', $this->site->id)->where('status', 'pending')->latest()->limit(3)->get();
        $lowUnanswered = Review::where('site_id', $this->site->id)->where('rating', '<=', 3)
            ->whereNull('reply_body')->where('status', '<>', 'hidden')->latest()->limit(3)->get();
        $photo = ReviewService::photoResolver($this->site, $reviews->concat($pendingList));

        $requests = collect();
        $contacts = collect();
        if ($this->tab === 'requests') {
            $requests = ReviewRequest::where('site_id', $this->site->id)->latest()->limit($this->requestsLimit + 1)->get();
            $s = trim($this->contactSearch);
            $contacts = Contact::where('site_id', $this->site->id)->whereNotNull('email')->where('email', '<>', '')
                ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                ->orderByDesc('last_activity_at')->limit(40)->get(['id', 'name', 'email']);
        }

        // "Show reviews on your site": a ready JSON-LD snippet of the top published reviews.
        $ldReviews = Review::where('site_id', $this->site->id)->published()->orderByDesc('featured')->latest()->limit(5)->get();
        $ld = ReviewService::jsonLdScript(ReviewService::jsonLd($this->site, $aggregate, $ldReviews), true);

        return view('livewire.reviews-page', [
            'stats' => $stats,
            'aggregate' => $aggregate,
            'reviews' => $reviews,
            'hasMore' => $hasMore,
            'pendingList' => $pendingList,
            'lowUnanswered' => $lowUnanswered,
            'photo' => $photo,
            'requests' => $requests->take($this->requestsLimit),
            'moreRequests' => $requests->count() > $this->requestsLimit,
            'contacts' => $contacts,
            'canManage' => $this->canManage(),
            'apiUrl' => url('/api/sites/'.$this->site->name.'/reviews'),
            'ldSnippet' => $ld,
        ]);
    }
}
