<?php

namespace App\Jobs\Email;

use App\Models\MailboxAlias;
use App\Services\Email\EmailProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Add or remove an alias at the provider. */
class MailboxAliasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600];

    public function __construct(public string $aliasId, public string $op) {}

    public function handle(EmailProvider $provider): void
    {
        $a = MailboxAlias::with(['mailbox', 'emailDomain'])->find($this->aliasId);
        if (! $a) {
            return;
        }
        $ctx = ['account_id' => $a->mailbox?->account_id];

        if ($this->op === 'remove') {
            if ($a->provider_reference) {
                $provider->deleteAlias($a->provider_reference, $ctx);
            }
            $a->delete();

            return;
        }
        if ($a->status !== 'pending') {
            return;
        }
        if ($a->mailbox->status === 'pending') {
            $this->release(60);

            return;
        }
        $ref = $provider->addAlias($a->emailDomain->domain, $a->mailbox->local_part, $a->local_part, $ctx);
        $a->update(['provider_reference' => $ref, 'status' => 'active', 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        MailboxAlias::whereKey($this->aliasId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }
}
