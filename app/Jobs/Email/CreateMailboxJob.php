<?php

namespace App\Jobs\Email;

use App\Models\Mailbox;
use App\Models\Site;
use App\Services\Email\EmailProvider;
use App\Support\TaskAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Create a mailbox at the provider. Idempotent: an existing mailbox is
 * adopted, and a licence already bought (provider_state) isn't bought again.
 * Encrypted on the queue because it carries the initial password.
 */
class CreateMailboxJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public int $uniqueFor = 3600;

    public function __construct(public string $mailboxId, public string $password) {}

    public function uniqueId(): string
    {
        return $this->mailboxId;
    }

    public function handle(EmailProvider $provider): void
    {
        $m = Mailbox::with('emailDomain')->find($this->mailboxId);
        if (! $m || $m->status !== 'pending') {
            return;
        }
        $d = $m->emailDomain;
        if (! $d->provider_reference) {
            $this->release(60); // the domain is still being switched on

            return;
        }

        $box = $provider->createMailbox(
            $d->domain, $m->local_part, (string) ($m->display_name ?: $m->local_part), $this->password,
            $m->provider_state === 'licence_ordered',
            fn () => $m->update(['provider_state' => 'licence_ordered']),
            ['account_id' => $m->account_id],
        );

        $m->update([
            'provider_reference' => $box['reference'],
            'provider_state' => 'created',
            'status' => 'active',
            'quota_used_mb' => isset($box['used_mb']) ? (int) $box['used_mb'] : null,
            'error' => null,
            'synced_at' => now(),
        ]);
        TaskAlerts::done($m->created_by ?: $m->account_id, $d->site_id, 'Mailbox ready: '.$m->local_part.'@'.$d->domain,
            'It can send and receive mail now.', $this->link($d), ['mailbox_id' => $m->id]);
    }

    private function link($d): ?string
    {
        $name = $d->site_id ? Site::whereKey($d->site_id)->value('name') : null;

        return $name ? url($name.'/mailboxes') : null;
    }

    public function failed(Throwable $e): void
    {
        $updated = Mailbox::whereKey($this->mailboxId)->where('status', 'pending')
            ->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        $m = $updated ? Mailbox::with('emailDomain')->find($this->mailboxId) : null;
        if ($m && $m->emailDomain) {
            TaskAlerts::failed($m->created_by ?: $m->account_id, $m->emailDomain->site_id, 'Mailbox couldn\'t be created: '.$m->local_part.'@'.$m->emailDomain->domain,
                'Delete it and try again, or contact us if it keeps failing.', $this->link($m->emailDomain), ['mailbox_id' => $m->id]);
        }
    }
}
