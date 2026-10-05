<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local/dev + test provider: remembers what was created (cache) and never
 * calls out. Records every call in $calls so tests can assert on them.
 */
class FakeEmailProvider implements EmailProvider
{
    public array $calls = [];

    /** Make the next call(s) of this method throw (tests). */
    public array $failNext = [];

    private function state(): array
    {
        return Cache::get('fake-email-provider', ['domains' => [], 'mailboxes' => [], 'aliases' => []]);
    }

    private function save(array $s): void
    {
        Cache::forever('fake-email-provider', $s);
    }

    private function call(string $method, array $args): void
    {
        $this->calls[] = [$method, $args];
        if (($this->failNext[$method] ?? 0) > 0) {
            $this->failNext[$method]--;
            throw new \RuntimeException("Fake provider: {$method} failed");
        }
    }

    public function addDomain(string $domain, array $context = []): string
    {
        $this->call('addDomain', [$domain]);
        $s = $this->state();
        $s['domains'][strtolower($domain)] = true;
        $this->save($s);

        return strtolower($domain);
    }

    public function removeDomain(string $domain, array $context = []): void
    {
        $this->call('removeDomain', [$domain]);
        $s = $this->state();
        unset($s['domains'][strtolower($domain)]);
        $this->save($s);
    }

    public function dkimRecord(string $domain, array $context = []): ?array
    {
        $this->call('dkimRecord', [$domain]);

        return ['name' => 'dkim._domainkey', 'type' => 'TXT', 'value' => 'v=DKIM1; k=rsa; p=FAKEKEY'];
    }

    public function findMailbox(string $domain, string $localPart, array $context = []): ?array
    {
        $key = strtolower($localPart.'@'.$domain);

        return $this->state()['mailboxes'][$key] ?? null;
    }

    public function createMailbox(string $domain, string $localPart, string $displayName, string $password, bool $licenceOrdered, callable $onLicence, array $context = []): array
    {
        if ($existing = $this->findMailbox($domain, $localPart)) {
            return $existing;
        }
        if (! $licenceOrdered) {
            $this->call('orderLicence', [$domain, $localPart]);
            $onLicence();
        }
        $this->call('createMailbox', [$domain, $localPart, $displayName]);
        $s = $this->state();
        $box = ['reference' => (string) random_int(100000, 999999), 'quota_gb' => 15.0, 'used_mb' => 0.0];
        $s['mailboxes'][strtolower($localPart.'@'.$domain)] = $box;
        $this->save($s);

        return $box;
    }

    public function deleteMailbox(string $reference, array $context = []): void
    {
        $this->call('deleteMailbox', [$reference]);
        $s = $this->state();
        $s['mailboxes'] = array_filter($s['mailboxes'], fn ($b) => $b['reference'] !== $reference);
        $this->save($s);
    }

    public function setPassword(string $domain, string $localPart, string $password, array $context = []): void
    {
        $this->call('setPassword', [$domain, $localPart]);
    }

    public function setDisplayName(string $domain, string $localPart, string $displayName, array $context = []): void
    {
        $this->call('setDisplayName', [$domain, $localPart, $displayName]);
    }

    public function usage(string $domain, string $localPart, array $context = []): ?array
    {
        return $this->findMailbox($domain, $localPart) ? ['quota_gb' => 15.0, 'used_mb' => 42.0] : null;
    }

    public function addAlias(string $domain, string $mailboxLocalPart, string $aliasLocalPart, array $context = []): string
    {
        $this->call('addAlias', [$domain, $mailboxLocalPart, $aliasLocalPart]);

        return 'alias-'.Str::lower(Str::random(8));
    }

    public function deleteAlias(string $reference, array $context = []): void
    {
        $this->call('deleteAlias', [$reference]);
    }

    public function addForward(string $domain, string $localPart, string $target, array $context = []): void
    {
        throw new UnsupportedByProvider('Forwarding is not available yet.');
    }

    public function suspendMailbox(string $reference, array $context = []): void
    {
        throw new UnsupportedByProvider('Suspending at the provider is not available yet.');
    }
}
