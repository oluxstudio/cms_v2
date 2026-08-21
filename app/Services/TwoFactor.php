<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor via any authenticator app (Google Authenticator, Authy,
 * 1Password…). The shared secret is stored ENCRYPTED on the user; enrollment
 * is only complete once a live code has been confirmed.
 */
class TwoFactor
{
    public function __construct(private Google2FA $engine) {}

    /** Start (or restart) enrollment: mint and store a fresh secret. */
    public function issueSecret(User $user): string
    {
        $secret = $this->engine->generateSecretKey(32);
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_enabled' => false,
        ])->save();

        return $secret;
    }

    public function secretFor(User $user): ?string
    {
        return $user->two_factor_secret ? Crypt::decryptString($user->two_factor_secret) : null;
    }

    /** otpauth:// provisioning URI encoded as an inline SVG QR code. */
    public function qrSvg(User $user): string
    {
        $uri = $this->engine->getQRCodeUrl(config('app.name', 'Olux'), $user->email, (string) $this->secretFor($user));
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        // Strip the XML declaration so the SVG can be inlined in blade.
        return trim(preg_replace('/^<\?xml.*?\?>/', '', $writer->writeString($uri)));
    }

    /** Check a 6-digit code against the user's secret (±1 time window). */
    public function verify(User $user, string $code): bool
    {
        $secret = $this->secretFor($user);

        return $secret !== null
            && (bool) $this->engine->verifyKey($secret, preg_replace('/\D/', '', $code));
    }

    /** Called after the FIRST valid code: enrollment is now trusted. */
    public function confirm(User $user): void
    {
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled' => true,
        ])->save();
    }

    public function enrolled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }
}
