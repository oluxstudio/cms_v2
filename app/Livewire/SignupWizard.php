<?php

namespace App\Livewire;

use App\Models\Site;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\Blueprints\BlueprintRegistry;
use App\Services\SignupVerification;
use App\Services\TemplateInstaller;
use App\Support\TemplateCards;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Self-serve signup: account → business (type, template, address) → verify → dashboard.
 *
 * The account exists from step 1 (so progress is saved and abandoners can be
 * recovered by signup:nudge); the site is provisioned from the blueprint for
 * the business type — with the template the user picked — at step 2; the
 * email code at step 3 only gates the dashboard. No card, no preview, no
 * go-live here: plans, custom domains and going live all happen inside the
 * CMS once the account is verified. Every account starts on the free trial.
 */
class SignupWizard extends Component
{
    public const STEPS = ['account', 'business', 'verify', 'done'];

    public int $step = 1;

    /** Rendered inside the auth card shell (no outer wrapper/card of its own). */
    public bool $embedded = false;

    // Step 1
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Step 2
    #[Url(as: 'type')]
    public string $type = '';

    public string $business = '';

    public string $subdomain = '';

    /** What the site is for, in the owner's words — saved on the site and shown in the checklist. */
    public string $purpose = '';

    /** null = not checked, true/false = availability of $subdomain */
    public ?bool $available = null;

    // Step 3
    public string $code = '';

    public ?string $siteId = null;

    public function mount(SignupVerification $codes): void
    {
        if ($this->type === '' && session()->has('signup_type')) {
            $this->type = (string) session()->pull('signup_type');
        }
        if (! BlueprintRegistry::exists($this->type)) {
            $this->type = '';
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        // Invited members (own nothing, member somewhere) skip the wizard.
        if (! $user->sites()->exists() && $user->memberships()->exists()) {
            $this->redirect($user->landingUrl(), navigate: true);

            return;
        }

        // Returning mid-wizard (or a logged-in user who never finished).
        $state = (array) (($user->onboarding ?? [])['wizard'] ?? []);
        $this->siteId = $state['site_id'] ?? null;
        $this->type = $state['type'] ?? $this->type;
        $this->business = $state['business'] ?? '';

        if (! empty($state['done'])) {
            $this->redirect($this->dashboardUrl() ?? route('home'), navigate: true);

            return;
        }

        $this->step = max(2, (int) ($state['step'] ?? 2));
        if ($this->step >= 3 && $user->email_verified_at) {
            $this->step = 4;
        } elseif ($this->step === 3 && ! $codes->pendingForUser($user)) {
            $codes->startForUser($user); // resumed on the verify step: make sure a code is in flight
        }
    }

    // ── Step 1: account ──────────────────────────────────────────────────

    public function createAccount(SignupVerification $codes): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'password_changed_at' => now(),
        ]);
        event(new Registered($user));
        Auth::login($user);
        session()->regenerate();

        $this->password = $this->password_confirmation = '';
        $this->go(2);
    }

    // ── Step 2: business + subdomain ─────────────────────────────────────

    public function updatedBusiness(string $value): void
    {
        // Suggest a subdomain from the business name until the user edits it.
        if ($this->subdomain === '' || $this->subdomain === $this->slug($this->previousBusiness ?? '')) {
            $this->subdomain = $this->slug($value);
        }
        $this->previousBusiness = $value;
        $this->checkAvailability();
    }

    /** Remembered so an auto-suggested subdomain keeps following the name. */
    public ?string $previousBusiness = null;

    public function updatedSubdomain(string $value): void
    {
        $this->subdomain = $this->slug($value);
        $this->checkAvailability();
    }

    public function checkAvailability(): void
    {
        $label = $this->subdomain;
        $this->available = $label !== ''
            && Site::validSubdomainLabel($label)
            && ! Site::where('name', $label)->exists();
    }

    public function createSite(SignupVerification $codes): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->subdomain = $this->slug($this->subdomain);
        $this->validate([
            'type' => ['required', 'string'],
            'business' => ['required', 'string', 'min:2', 'max:80'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'subdomain' => ['required', 'string', 'min:3', 'max:63'],
        ]);
        if (! BlueprintRegistry::exists($this->type)) {
            $this->addError('type', 'Pick the kind of business you run.');

            return;
        }
        $this->checkAvailability();
        if (! $this->available) {
            $this->addError('subdomain', 'That address is taken or not allowed — try another.');

            return;
        }
        if (! $user->currentSubscription()->canCreateSite()) {
            $this->addError('subdomain', 'Your plan has no room for another site.');

            return;
        }

        $site = Site::create([
            'name' => $this->subdomain,
            'domain' => $this->subdomain.'.'.((string) config('publishing.subdomain_base') ?: 'test'),
            'owner' => $this->business,
            'user_id' => $user->id,
        ]);
        $site->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        $site->setAttr('business_name', $this->business);
        $site->setAttr('business_type', $this->type);
        if (trim($this->purpose) !== '') {
            $site->setAttr('site_purpose', trim($this->purpose));
            $site->update(['description' => Str::limit(trim($this->purpose), 250)]);
        }

        BlueprintRegistry::forType($this->type)->apply($site);

        // A design picked in the public gallery before signing up lands here.
        if ($pending = session()->pull('pending_template')) {
            try {
                if ($resolved = TemplateCards::resolve($pending)) {
                    [$card, $tpl] = $resolved;
                    $tpl
                        ? app(TemplateInstaller::class)->saveCatalogToSite($user, $site, $tpl)
                        : app(TemplateInstaller::class)->saveCuratedToSite($site, $card['builtin']);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        AccountActivity::siteCreated($site);
        $this->createSetupTodo($site, $user);
        $this->siteId = $site->id;

        // ── Step 3: verify (skipped when the email is already confirmed) ──
        if ($user->email_verified_at) {
            $this->go(4);

            return;
        }
        if (! $codes->pendingForUser($user)) {
            $codes->startForUser($user);
        }
        $this->go(3);
    }

    public function verify(SignupVerification $codes): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->validate(['code' => ['required', 'string']]);

        $codes->verifyForUser($user, $this->code);
        $this->code = '';
        $this->go(4);
    }

    public function resend(SignupVerification $codes): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $codes->resendForUser($user);
        session()->flash('code-resent', 'A new code is on its way.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** The post-signup checklist in the site's Tasks panel: what to finish inside the CMS. */
    private function createSetupTodo(Site $site, User $user): void
    {
        $todo = $site->todos()->create([
            'user_id' => $user->id,
            'assigned_user_id' => $user->id,
            'title' => 'Finish setting up '.$this->business,
            'description' => 'Everything your new site still needs — work through these from the dashboard.',
            'priority' => 'high',
            'status' => 'open',
        ]);
        foreach (self::SETUP_ITEMS as $i => $label) {
            $todo->items()->create(['label' => $label, 'sort' => $i + 1]);
        }
    }

    /** Checklist items added to every new site (order matters). */
    public const SETUP_ITEMS = [
        'Set your domain name — buy one or connect one you own (Go live page)',
        'Pick a template for your site',
        'Review the colour theme, logo and fonts',
        'Choose the features your site needs (bookings, store, invoices, forms…)',
        'Add your services, prices and opening hours',
        'Pick a plan before your free trial ends',
    ];

    /** Social providers with credentials configured — shown as one-click signup. */
    public function socialProviders(): array
    {
        return array_values(array_filter(['google', 'facebook'], fn ($p) => filled(config("services.{$p}.client_id"))));
    }

    public function todosUrl(): ?string
    {
        $site = $this->site();

        return $site ? url("/{$site->name}/tasks") : null;
    }

    public function site(): ?Site
    {
        return $this->siteId ? Site::where('user_id', Auth::id())->find($this->siteId) : null;
    }

    /** The address shown at the end: the instant subdomain (custom domains come later, in the CMS). */
    public function siteUrl(): ?string
    {
        return $this->site()?->publicUrl();
    }

    public function dashboardUrl(): ?string
    {
        $site = $this->site();

        return $site ? url("/{$site->name}/dashboard") : null;
    }

    private function go(int $step): void
    {
        $this->step = $step;
        $this->resetErrorBag();
        Auth::user()?->setOnboarding(['wizard' => [
            'step' => $step,
            'site_id' => $this->siteId,
            'type' => $this->type,
            'business' => $this->business,
            'done' => $step >= 4,
            'updated_at' => now()->toIso8601String(),
        ]]);
    }

    private function slug(string $value): string
    {
        return Str::limit(Str::slug($value), 63, '');
    }

    public function render()
    {
        return view('livewire.signup-wizard', [
            'types' => BlueprintRegistry::types(),
            'base' => (string) config('publishing.subdomain_base'),
            'trialDays' => (int) config('plans.trial_days', 14),
        ]);
    }
}
