<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Subscription;
use App\Modules\Newsletter\Models\NewsletterSend;
use App\Modules\Newsletter\Newsletter;
use App\Modules\Newsletter\Services\SubscriberService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Links inside newsletter emails (no auth, no session needed):
 *   GET  /newsletter/confirm/{token}       double opt-in confirmation
 *   GET  /newsletter/unsubscribe/{token}   "Unsubscribe?" page with a button
 *   POST /newsletter/unsubscribe/{token}   the button, and RFC 8058 one-click (CSRF-exempt)
 *   GET  /newsletter/o/{send}.gif          open pixel
 *   GET  /newsletter/c/{send}?u=…          signed click redirect
 */
class NewsletterPublicController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function confirm(string $token, SubscriberService $subscribers)
    {
        $sub = Subscription::where('token', $token)->firstOrFail();
        $subscribers->confirm($sub);

        return $this->page($sub, $sub->isActive() ? 'confirmed' : 'inactive');
    }

    public function showUnsubscribe(Request $request, string $token)
    {
        $sub = Subscription::where('token', $token)->firstOrFail();

        return $this->page($sub, $sub->status === Subscription::UNSUBSCRIBED ? 'unsubscribed' : 'ask', (string) $request->query('c'));
    }

    public function unsubscribe(Request $request, string $token, SubscriberService $subscribers)
    {
        $sub = Subscription::where('token', $token)->firstOrFail();
        $sendToken = (string) ($request->query('c') ?: $request->input('c'));
        $send = $sendToken !== '' ? NewsletterSend::where('token', $sendToken)->first() : null;

        $subscribers->unsubscribe($sub, $send);

        // Mail providers' one-click POST (RFC 8058) just needs a 2xx.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('Unsubscribed', 200)->header('Content-Type', 'text/plain');
        }

        return $this->page($sub->fresh(), 'unsubscribed');
    }

    public function open(string $send): Response
    {
        NewsletterSend::where('token', $send)->first()?->recordOpen();

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /** Signed (route middleware), so the target can't be swapped: no open redirect. */
    public function click(Request $request, string $send): RedirectResponse
    {
        $target = (string) $request->query('u');
        abort_unless(preg_match('#^https?://#i', $target), 404);

        NewsletterSend::where('token', $send)->first()?->recordClick();

        return redirect()->away($target);
    }

    private function page(Subscription $sub, string $state, string $sendToken = '')
    {
        $site = Site::find($sub->site_id);

        return response()->view('newsletter-public', [
            'state' => $state,
            'subscription' => $sub,
            'siteName' => $site ? Newsletter::siteName($site) : 'this newsletter',
            'logo' => $site?->brandLogo() ?? '',
            'sendToken' => $sendToken,
        ]);
    }
}
