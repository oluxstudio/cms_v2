<?php

namespace App\Jobs\Email;

use App\Models\Mailbox;
use App\Services\Email\EmailProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Password or display-name change at the provider. Encrypted (may carry a password). */
class UpdateMailboxJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600];

    public function __construct(public string $mailboxId, public string $what, public string $value) {}

    public function handle(EmailProvider $provider): void
    {
        $m = Mailbox::with('emailDomain')->find($this->mailboxId);
        if (! $m) {
            return;
        }
        if ($m->status === 'pending') {
            $this->release(60); // not created yet

            return;
        }
        $ctx = ['account_id' => $m->account_id];
        $this->what === 'password'
            ? $provider->setPassword($m->emailDomain->domain, $m->local_part, $this->value, $ctx)
            : $provider->setDisplayName($m->emailDomain->domain, $m->local_part, $this->value, $ctx);
        $m->update(['error' => null, 'synced_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Mailbox::whereKey($this->mailboxId)->update(['error' => 'Couldn\'t update the '.($this->what === 'password' ? 'password' : 'name').': '.mb_substr($e->getMessage(), 0, 500)]);
    }
}
