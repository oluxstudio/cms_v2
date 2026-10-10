<?php

namespace App\Modules\Network\Http;

use App\Http\Controllers\Controller;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\NetworkException;
use App\Modules\Network\ReferralManager;
use App\Support\SiteProperties;
use Illuminate\Http\Request;

/**
 * The page a referral consent email links to: /referral/{token}.
 * "May {referrer} pass your details to {receiver}?" — Yes / No, once.
 * Afterwards the link shows the answered (or expired) state.
 */
class ConsentController extends Controller
{
    public function __construct(private ReferralService $network) {}

    public function show(string $token)
    {
        return $this->page($this->find($token));
    }

    public function store(Request $request, string $token)
    {
        $referral = $this->find($token);
        $data = $request->validate(['answer' => ['required', 'in:yes,no']]);

        try {
            $this->network->recordConsent($token, $data['answer'] === 'yes', $request->ip());
        } catch (NetworkException $e) {
            return redirect()->route('network.consent.show', $token)->with('network.error', $e->getMessage());
        }

        return redirect()->route('network.consent.show', $token)->with('network.answered', $data['answer']);
    }

    private function find(string $token): Referral
    {
        $referral = $this->network->findByConsentToken($token);
        abort_unless($referral && $referral->fromSite && $referral->toSite, 404);

        return $referral;
    }

    private function page(Referral $referral)
    {
        $from = $referral->fromSite;
        $to = $referral->toSite;
        $pending = $referral->status === 'pending_consent';
        $expired = $referral->status === 'expired' || $referral->status === 'cancelled'
            || ($pending && $referral->expires_at && $referral->expires_at->isPast());

        $state = match (true) {
            $expired => 'expired',
            $pending => 'ask',
            $referral->status === 'consent_refused' => 'refused',
            default => 'shared',
        };

        $shared = ['Your name', 'Your email address'];
        if ($referral->customer_phone) {
            $shared[] = 'Your phone number';
        }
        if ($referral->note) {
            $shared[] = 'A short note from '.ReferralManager::businessName($from).' about what you need';
        }

        return view('network.consent', [
            'referral' => $referral,
            'state' => $state,
            'fromName' => ReferralManager::businessName($from),
            'toName' => ReferralManager::businessName($to),
            'toPitch' => $this->network->profileFor($to)?->pitch,
            'toArea' => $this->network->profileFor($to)?->area,
            'logo' => SiteProperties::imageUrl($from, SiteProperties::value($from, 'logo')),
            'shared' => $shared,
        ]);
    }
}
