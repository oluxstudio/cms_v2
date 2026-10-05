<?php

namespace App\Jobs\Email;

use App\Models\Mailbox;
use App\Services\Email\EmailProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Delete a mailbox (and all its mail) at the provider, then our rows. */
class DeleteMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public string $mailboxId) {}

    public function handle(EmailProvider $provider): void
    {
        $m = Mailbox::with('emailDomain')->find($this->mailboxId);
        if (! $m) {
            return;
        }
        $reference = $m->provider_reference
            ?: $provider->findMailbox($m->emailDomain->domain, $m->local_part, ['account_id' => $m->account_id])['reference'] ?? null;
        if ($reference) {
            $provider->deleteMailbox($reference, ['account_id' => $m->account_id]);
        }
        $m->aliases()->delete();
        $m->delete();
    }

    public function failed(Throwable $e): void
    {
        Mailbox::whereKey($this->mailboxId)->update(['error' => 'Deleting failed: '.mb_substr($e->getMessage(), 0, 500)]);
    }
}
