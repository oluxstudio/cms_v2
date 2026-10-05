<?php

namespace App\Jobs\Email;

use App\Models\EmailDomain;
use App\Services\Email\EmailDns;
use App\Services\Email\EmailProvider;
use App\Services\Openprovider\OpenproviderClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/** Switch email on at the provider, write DNS when we host it, then start DNS checks. Safe to retry. */
class EnableEmailDomainJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public int $uniqueFor = 900;

    public function __construct(public string $emailDomainId) {}

    public function uniqueId(): string
    {
        return $this->emailDomainId;
    }

    public function handle(EmailProvider $provider, EmailDns $dns): void
    {
        $d = EmailDomain::find($this->emailDomainId);
        if (! $d || ! in_array($d->status, ['pending_dns', 'failed'], true)) {
            return;
        }
        $ctx = ['account_id' => $d->account_id, 'description' => 'Site '.$d->site?->name];

        if (! $d->provider_reference) {
            try {
                $ref = $provider->addDomain($d->domain, $ctx);
            } catch (RuntimeException $e) {
                // A retry after the provider already added it: carry on.
                if (! preg_match('/already|exist/i', $e->getMessage())) {
                    throw $e;
                }
                $ref = strtolower($d->domain);
            }
            $d->update(['provider_reference' => $ref, 'error' => null]);
        }

        $dns->dkim($d->fresh());

        if ($d->source === 'openprovider' && config('email.driver') === 'openprovider') {
            $dns->applyToOpenproviderZone($d->fresh(), new OpenproviderClient(config('openprovider')));
        }

        $d->update(['status' => 'verifying', 'dns_attempts' => 0]);
        CheckEmailDnsJob::dispatch($d->id);
    }

    public function failed(Throwable $e): void
    {
        EmailDomain::whereKey($this->emailDomainId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }
}
