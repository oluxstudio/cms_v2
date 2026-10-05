<?php

namespace App\Livewire;

use App\Models\EmailDomain;
use App\Models\Mailbox;
use App\Models\MailboxAlias;
use App\Models\Site;
use App\Services\Email\BusinessEmail;
use App\Services\Email\EmailDns;
use App\Services\Email\MailboxAddons;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Business email for the site's domain: switch it on, publish/verify DNS,
 * and create / manage mailboxes and aliases. Every rule lives in
 * BusinessEmail (server-side); this component only gathers input. All rows
 * are looked up through the site's own account — never by bare id.
 */
class SiteMailboxesPage extends Component
{
    public Site $site;

    // Switch on
    public bool $confirmMx = false;

    // New mailbox
    public bool $creating = false;

    public string $localPart = '';

    public string $displayName = '';

    public string $passwordMode = 'generate';

    public string $ownPassword = '';

    /** A password shown ONCE after create/reset: ['address' => …, 'password' => …] */
    public ?array $shownPassword = null;

    // Manage
    public array $names = [];

    public array $aliasInput = [];

    public ?string $deleteId = null;

    public string $deleteTyped = '';

    public int $addonQty = 1;

    public function mount(Site $site): void
    {
        abort_unless($site->allows(Auth::user(), 'email.manage'), 403);
        $this->site = $site;
    }

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'email.manage'), 403);
    }

    /** This site's email domain — tenant-scoped (the site's own account). */
    public function getEmailDomainProperty(): ?EmailDomain
    {
        return $this->site->domain
            ? EmailDomain::forAccount($this->site->user_id)->where('site_id', $this->site->id)->where('domain', strtolower($this->site->domain))->first()
            : null;
    }

    /** A mailbox of THIS account on THIS domain, or 404. */
    private function mailbox(string $id): Mailbox
    {
        $d = $this->emailDomain;
        abort_unless($d, 404);

        return Mailbox::forAccount($this->site->user_id)->where('email_domain_id', $d->id)->with('emailDomain')->find($id) ?? abort(404);
    }

    private function run(callable $fn, string $okTitle, ?string $okMessage = null): mixed
    {
        $this->guard();
        try {
            $result = $fn(app(BusinessEmail::class));
            if ($okTitle !== '') {
                $this->dispatch('toast', level: 'success', title: $okTitle, message: $okMessage ?? '');
            }

            return $result;
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? $e->errors());
            $this->dispatch('toast', level: 'error', title: 'Not done', message: collect($e->errors())->flatten()->first());

            return null;
        }
    }

    // ── Domain ─────────────────────────────────────────────────────

    public function enable(): void
    {
        $this->run(fn (BusinessEmail $svc) => $svc->enable($this->site, Auth::user(), $this->confirmMx),
            'Business email is being switched on', 'We\'ll check your domain\'s records and let you know when it\'s ready.');
    }

    public function recheck(): void
    {
        if ($d = $this->emailDomain) {
            $this->run(fn (BusinessEmail $svc) => $svc->recheckDns($d), 'Checking your records', 'This can take a few minutes after you add them.');
        }
    }

    // ── Mailboxes ──────────────────────────────────────────────────

    public function createMailbox(): void
    {
        $d = $this->emailDomain;
        abort_unless($d, 404);
        $result = $this->run(fn (BusinessEmail $svc) => $svc->createMailbox(
            $d, Auth::user(), $this->localPart, $this->displayName, $this->passwordMode === 'own' ? $this->ownPassword : null,
        ), '');
        if ($result) {
            [$mailbox, $password] = $result;
            $this->shownPassword = ['address' => $mailbox->local_part.'@'.$d->domain, 'password' => $password];
            $this->reset(['localPart', 'displayName', 'ownPassword', 'creating']);
            $this->passwordMode = 'generate';
        }
    }

    public function resetPassword(string $id): void
    {
        $m = $this->mailbox($id);
        $password = $this->run(fn (BusinessEmail $svc) => $svc->resetPassword($m, Auth::user()), '');
        if ($password) {
            $this->shownPassword = ['address' => $m->address(), 'password' => $password];
        }
    }

    public function dismissPassword(): void
    {
        $this->shownPassword = null;
    }

    public function saveName(string $id): void
    {
        $m = $this->mailbox($id);
        $this->run(fn (BusinessEmail $svc) => $svc->rename($m, Auth::user(), (string) ($this->names[$id] ?? '')), 'Name updated');
        unset($this->names[$id]);
    }

    public function addAlias(string $id): void
    {
        $m = $this->mailbox($id);
        if ($this->run(fn (BusinessEmail $svc) => $svc->addAlias($m, Auth::user(), (string) ($this->aliasInput[$id] ?? '')), 'Alias added')) {
            unset($this->aliasInput[$id]);
        }
    }

    public function removeAlias(string $aliasId): void
    {
        $d = $this->emailDomain;
        abort_unless($d, 404);
        $a = MailboxAlias::where('email_domain_id', $d->id)->whereHas('mailbox', fn ($q) => $q->where('account_id', $this->site->user_id))->find($aliasId) ?? abort(404);
        $this->run(fn (BusinessEmail $svc) => $svc->removeAlias($a, Auth::user()), 'Alias removed');
    }

    public function askDelete(string $id): void
    {
        $this->mailbox($id);
        $this->deleteId = $id;
        $this->deleteTyped = '';
        $this->resetErrorBag('confirm_address');
    }

    public function confirmDelete(): void
    {
        $m = $this->mailbox((string) $this->deleteId);
        $done = $this->run(fn (BusinessEmail $svc) => $svc->deleteMailbox($m, Auth::user(), $this->deleteTyped) ?? true, 'Mailbox deleted', $m->address().' and all its mail are being removed.');
        if ($done) {
            $this->reset(['deleteId', 'deleteTyped']);
        }
    }

    public function buyAddon(): void
    {
        $this->guard();
        try {
            app(MailboxAddons::class)->buy($this->site->user, Auth::user(), (int) $this->addonQty);
            $this->dispatch('toast', level: 'success', title: 'Mailboxes added', message: "{$this->addonQty} more ".Str::plural('mailbox', $this->addonQty).' added to your plan.');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
        }
    }

    public function render(EmailDns $dns, BusinessEmail $svc)
    {
        $d = $this->emailDomain;
        $sub = $this->site->user?->currentSubscription();
        $mailboxes = $d ? $d->mailboxes()->with('aliases')->orderBy('local_part')->get() : collect();
        $foreignMx = ! $d && $this->site->domain && ! $svc->ineligibility($this->site) ? $dns->currentMx($this->site->domain) : [];
        $records = $d ? ($d->dns_report['records'] ?? $dns->desiredRecords($d, $dns->currentSpf($d->domain))) : [];

        return view('livewire.site-mailboxes-page', [
            'd' => $d,
            'sub' => $sub,
            'limit' => $sub?->mailboxLimit() ?? 0,
            'used' => $sub?->mailboxesUsed() ?? 0,
            'mailboxes' => $mailboxes,
            'ineligible' => $d ? null : $svc->ineligibility($this->site),
            'foreignMx' => collect($foreignMx)->reject(fn ($h) => in_array($h, collect((array) config('email.records.mx'))->pluck('host')->all(), true))->values()->all(),
            'records' => $records,
            'addonAvailable' => $this->site->user ? app(MailboxAddons::class)->available($this->site->user) : false,
            'busy' => ($d && in_array($d->status, ['pending_dns', 'verifying'], true)) || $mailboxes->contains(fn ($m) => in_array($m->status, ['pending', 'deleting'], true))
                || $mailboxes->flatMap->aliases->contains(fn ($a) => in_array($a->status, ['pending', 'deleting'], true)),
        ]);
    }
}
