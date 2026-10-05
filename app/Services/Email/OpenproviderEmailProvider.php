<?php

namespace App\Services\Email;

use App\Services\Domains\OpenproviderRegistrar;
use App\Services\Openprovider\OpenproviderClient;

/**
 * Openprovider Business Email ("Mailcow" API). Endpoints and payloads are the
 * ones in Openprovider's published OpenAPI spec (developer.openprovider.com);
 * anything the spec doesn't document is a marked TODO, never a guess.
 */
class OpenproviderEmailProvider implements EmailProvider
{
    public function __construct(private OpenproviderClient $client) {}

    private function parts(string $domain): array
    {
        return OpenproviderRegistrar::normalize($domain); // ['name' => …, 'extension' => …]
    }

    private function log(string $action, array $context): array
    {
        return ['action' => $action, 'account_id' => $context['account_id'] ?? null];
    }

    public function addDomain(string $domain, array $context = []): string
    {
        // POST /mailcow/domains {domain:{name,extension}, description, owner_handle}
        $this->client->send('post', '/mailcow/domains', array_filter([
            'domain' => $this->parts($domain),
            'description' => $context['description'] ?? null,
            'owner_handle' => $context['owner_handle'] ?? null,
        ]), $this->log('domain.add', $context));

        // Mailcow keys email domains by name; there is no separate id in the response.
        return strtolower($domain);
    }

    public function removeDomain(string $domain, array $context = []): void
    {
        $p = $this->parts($domain);
        // DELETE /mailcow/domains?domain.name=&domain.extension=
        $this->client->send('delete', '/mailcow/domains', ['domain.name' => $p['name'], 'domain.extension' => $p['extension']], $this->log('domain.remove', $context));
    }

    public function dkimRecord(string $domain, array $context = []): ?array
    {
        $p = $this->parts($domain);
        $get = fn () => $this->client->send('get', '/mailcow/dkim', ['domain.name' => $p['name'], 'domain.extension' => $p['extension']], $this->log('dkim.get', $context));
        $data = $get();
        if (blank($data['txt_record']['value'] ?? null)) {
            // POST /mailcow/dkim {domain, dkim_selector, key_size}
            $this->client->send('post', '/mailcow/dkim', ['domain' => $p, 'dkim_selector' => 'dkim', 'key_size' => 2048], $this->log('dkim.create', $context));
            $data = $get();
        }
        $rec = $data['txt_record'] ?? null;

        return filled($rec['value'] ?? null)
            ? ['name' => (string) $rec['name'], 'type' => (string) ($rec['record_type'] ?: 'TXT'), 'value' => (string) $rec['value']]
            : null;
    }

    public function findMailbox(string $domain, string $localPart, array $context = []): ?array
    {
        $p = $this->parts($domain);
        // GET /mailcow/orders?domain.name=&domain.extension=&mailbox=
        $data = $this->client->send('get', '/mailcow/orders', [
            'domain.name' => $p['name'], 'domain.extension' => $p['extension'], 'mailbox' => $localPart, 'limit' => 10,
        ], $this->log('mailbox.find', $context));
        foreach ((array) ($data['results'] ?? []) as $order) {
            if (strcasecmp((string) ($order['mailbox'] ?? ''), $localPart) === 0 || strcasecmp((string) ($order['mailbox'] ?? ''), $localPart.'@'.$domain) === 0) {
                return $this->mailboxShape($order);
            }
        }

        return null;
    }

    public function createMailbox(string $domain, string $localPart, string $displayName, string $password, bool $licenceOrdered, callable $onLicence, array $context = []): array
    {
        if ($existing = $this->findMailbox($domain, $localPart, $context)) {
            return $existing; // retried after the provider already made it
        }

        $period = (string) config('email.subscription_period', '1');
        if (! $licenceOrdered) {
            // POST /mailcow/orders {quantity, period} — buys ONE mailbox licence.
            // TODO(openprovider): the response is only {success}; reusing an unassigned
            // licence needs the mailbox_status values, which the spec doesn't list.
            $this->client->send('post', '/mailcow/orders', ['quantity' => '1', 'period' => $period], $this->log('mailbox.licence', $context));
            $onLicence();
        }

        // POST /mailcow/orders/assign {name, domain, mailbox, password, reset_password, subscription_period}
        $order = $this->client->send('post', '/mailcow/orders/assign', [
            'name' => $displayName,
            'domain' => $this->parts($domain),
            'mailbox' => $localPart,
            'password' => $password,
            'reset_password' => false,
            'subscription_period' => $period,
        ], $this->log('mailbox.create', $context));

        return $this->mailboxShape($order);
    }

    public function deleteMailbox(string $reference, array $context = []): void
    {
        // DELETE /mailcow/orders/{id}
        $this->client->send('delete', '/mailcow/orders/'.rawurlencode($reference), [], $this->log('mailbox.delete', $context));
    }

    public function setPassword(string $domain, string $localPart, string $password, array $context = []): void
    {
        // POST /mailcow/mailbox/edit {domain, mailbox, password, password_confirmation, reset_password}
        $this->client->send('post', '/mailcow/mailbox/edit', [
            'domain' => $this->parts($domain), 'mailbox' => $localPart,
            'password' => $password, 'password_confirmation' => $password, 'reset_password' => false,
        ], $this->log('mailbox.password', $context));
    }

    public function setDisplayName(string $domain, string $localPart, string $displayName, array $context = []): void
    {
        // POST /mailcow/mailbox/name/modify {domain, mailbox, name}
        $this->client->send('post', '/mailcow/mailbox/name/modify', [
            'domain' => $this->parts($domain), 'mailbox' => $localPart, 'name' => $displayName,
        ], $this->log('mailbox.rename', $context));
    }

    public function usage(string $domain, string $localPart, array $context = []): ?array
    {
        // quota / quota_used come on the order list (MailCowOrder).
        // TODO(openprovider): confirm their units — the spec doesn't say (GB/MB assumed).
        $box = $this->findMailbox($domain, $localPart, $context);

        return $box ? ['quota_gb' => $box['quota_gb'], 'used_mb' => $box['used_mb']] : null;
    }

    public function addAlias(string $domain, string $mailboxLocalPart, string $aliasLocalPart, array $context = []): string
    {
        // POST /mailcow/alias {domain, mailbox, alias}
        $this->client->send('post', '/mailcow/alias', [
            'domain' => $this->parts($domain), 'mailbox' => $mailboxLocalPart, 'alias' => $aliasLocalPart,
        ], $this->log('alias.add', $context));

        // The add response has no id; find it in GET /mailcow/alias.
        $list = $this->client->send('get', '/mailcow/alias', ['limit' => 500], $this->log('alias.find', $context));
        foreach ((array) ($list['results'] ?? []) as $a) {
            $address = strtolower((string) ($a['alias'] ?? ''));
            if ($address === strtolower($aliasLocalPart.'@'.$domain) || $address === strtolower($aliasLocalPart)) {
                return (string) $a['id'];
            }
        }

        return strtolower($aliasLocalPart.'@'.$domain); // TODO(openprovider): list may paginate past 500
    }

    public function deleteAlias(string $reference, array $context = []): void
    {
        // DELETE /mailcow/alias/{id}
        $this->client->send('delete', '/mailcow/alias/'.rawurlencode($reference), [], $this->log('alias.delete', $context));
    }

    public function addForward(string $domain, string $localPart, string $target, array $context = []): void
    {
        throw new UnsupportedByProvider('Forwarding is not in Openprovider\'s documented email API yet.'); // TODO(openprovider)
    }

    public function suspendMailbox(string $reference, array $context = []): void
    {
        throw new UnsupportedByProvider('Suspending is not in Openprovider\'s documented email API yet.'); // TODO(openprovider)
    }

    private function mailboxShape(array $order): array
    {
        return [
            'reference' => (string) ($order['id'] ?? ''),
            'quota_gb' => isset($order['quota']) ? (float) $order['quota'] : null,
            'used_mb' => isset($order['quota_used']) ? (float) $order['quota_used'] : null,
        ];
    }
}
