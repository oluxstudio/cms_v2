<?php

namespace App\Console\Commands;

use App\Jobs\Email\DeleteMailboxJob;
use App\Models\EmailDomain;
use App\Services\AccountActivity;
use App\Services\Email\EmailProvider;
use Illuminate\Console\Command;
use Throwable;

/**
 * After a cancelled subscription's export window ends, delete its business
 * email at the provider: every mailbox, then the email domain itself.
 */
class PurgeSuspendedEmail extends Command
{
    protected $signature = 'email:purge-suspended';

    protected $description = 'Delete suspended business email once its export window has ended';

    public function handle(EmailProvider $provider): int
    {
        $due = EmailDomain::where('status', 'suspended')->whereNotNull('delete_after')->where('delete_after', '<=', now())->get();
        foreach ($due as $d) {
            $remaining = $d->mailboxes()->count();
            foreach ($d->mailboxes()->get() as $m) {
                $m->update(['status' => 'deleting']);
                DeleteMailboxJob::dispatch($m->id);
            }
            if ($remaining > 0) {
                continue; // domain goes on the next run, once its mailboxes are gone
            }
            try {
                if ($d->provider_reference) {
                    $provider->removeDomain($d->domain, ['account_id' => $d->account_id]);
                }
                AccountActivity::record($d->account_id, 'email.purged', 'Business email removed for '.$d->domain.' (export window ended)', ['category' => 'email', 'icon' => 'trash']);
                $d->delete();
            } catch (Throwable $e) {
                report($e);
                $d->update(['error' => 'Removing the domain failed: '.mb_substr($e->getMessage(), 0, 500)]);
            }
        }
        $this->info("Checked {$due->count()} suspended email domain(s).");

        return self::SUCCESS;
    }
}
