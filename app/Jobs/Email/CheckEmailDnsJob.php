<?php

namespace App\Jobs\Email;

use App\Models\EmailDomain;
use App\Models\Site;
use App\Services\Email\EmailDns;
use App\Support\TaskAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Recheck a domain's email DNS with backoff until everything resolves, then mark it active. */
class CheckEmailDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $emailDomainId) {}

    public function handle(EmailDns $dns): void
    {
        $d = EmailDomain::find($this->emailDomainId);
        if (! $d || ! in_array($d->status, ['verifying', 'pending_dns'], true)) {
            return;
        }

        $result = $dns->check($d);
        $report = ['records' => $result['records']] + array_intersect_key((array) $d->dns_report, ['dkim' => 1]);
        $attempts = $d->dns_attempts + 1;

        if ($result['ok']) {
            $d->update(['status' => 'active', 'dns_report' => $report, 'dns_attempts' => $attempts, 'dns_checked_at' => now(), 'error' => null]);
            TaskAlerts::done($d->account_id, $d->site_id, 'Business email is on for '.$d->domain,
                'Your domain\'s records check out. You can create mailboxes now.', $this->link($d), ['email_domain_id' => $d->id]);

            return;
        }

        $cfg = config('email.dns_check');
        $d->update(['status' => 'verifying', 'dns_report' => $report, 'dns_attempts' => $attempts, 'dns_checked_at' => now(),
            'error' => $attempts >= $cfg['max_attempts'] ? 'Some records still aren\'t showing. Check they were added exactly as shown, then press "Check again".' : null]);
        if ($attempts === (int) $cfg['max_attempts']) {
            TaskAlerts::failed($d->account_id, $d->site_id, 'Business email needs attention: '.$d->domain,
                'Some DNS records still aren\'t showing. Check they were added exactly as shown, then press "Check again".', $this->link($d), ['email_domain_id' => $d->id]);
        }
        if ($attempts < $cfg['max_attempts']) {
            $backoff = $cfg['backoff'];
            self::dispatch($d->id)->delay(now()->addSeconds($backoff[min($attempts - 1, count($backoff) - 1)]));
        }
    }

    private function link(EmailDomain $d): ?string
    {
        $name = $d->site_id ? Site::whereKey($d->site_id)->value('name') : null;

        return $name ? url($name.'/mailboxes') : null;
    }
}
