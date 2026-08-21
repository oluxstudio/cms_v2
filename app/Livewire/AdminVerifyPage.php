<?php

namespace App\Livewire;

use App\Http\Middleware\EnsureSuperAdmin;
use App\Services\TwoFactor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Step-up gate in front of the platform /admin area. Two modes:
 *  - enroll: no confirmed authenticator yet → show the QR + confirm a code
 *  - challenge: already enrolled → just ask for the current 6-digit code
 * A valid code stamps the session; EnsureSuperAdmin trusts it for 12 hours.
 */
class AdminVerifyPage extends Component
{
    public string $code = '';

    public string $qrSvg = '';

    public string $secret = '';

    public bool $enrolling = false;

    /** Where to return after a successful check. */
    #[Url(as: 'to')]
    public string $returnTo = '';

    public function mount(TwoFactor $tfa): void
    {
        $user = Auth::user();
        abort_unless($user?->isSuper(), 403);

        $this->enrolling = ! $tfa->enrolled($user);
        if ($this->enrolling) {
            // (Re)issue a secret only while unconfirmed — refresh-safe.
            if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
                $tfa->issueSecret($user);
            }
            $this->secret = (string) $tfa->secretFor($user->refresh());
            $this->qrSvg = $tfa->qrSvg($user);
        }
    }

    public function verify(TwoFactor $tfa): void
    {
        $user = Auth::user();
        abort_unless($user?->isSuper(), 403);

        if (! $tfa->verify($user, $this->code)) {
            $this->addError('code', 'That code didn’t match — check your authenticator app and try again.');
            $this->code = '';

            return;
        }

        if ($this->enrolling) {
            $tfa->confirm($user);
        }
        session()->put(EnsureSuperAdmin::SESSION_KEY, now());

        $to = $this->returnTo !== '' ? $this->returnTo : route('admin.dashboard');
        // Only follow same-app URLs — the ?to param is user-controlled.
        if (! str_starts_with($to, url('/'))) {
            $to = route('admin.dashboard');
        }
        $this->redirect($to);
    }

    public function render()
    {
        return view('livewire.admin-verify-page');
    }
}
