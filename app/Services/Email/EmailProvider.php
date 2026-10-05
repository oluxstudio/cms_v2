<?php

namespace App\Services\Email;

/**
 * The business-email provider behind tenant mailboxes. Implementations must be
 * safe to call again after a failure (jobs retry): creating something that
 * already exists returns it rather than making a duplicate.
 */
interface EmailProvider
{
    /** Switch email on for a domain. Returns the provider's reference for it. */
    public function addDomain(string $domain, array $context = []): string;

    public function removeDomain(string $domain, array $context = []): void;

    /**
     * DKIM TXT record for the domain (created on first call).
     *
     * @return array{name:string, type:string, value:string}|null
     */
    public function dkimRecord(string $domain, array $context = []): ?array;

    /** An existing mailbox, or null. @return array{reference:string, quota_gb:?float, used_mb:?float}|null */
    public function findMailbox(string $domain, string $localPart, array $context = []): ?array;

    /**
     * Create a mailbox. `$licenceOrdered` tells a retry that the licence was
     * already bought, so it isn't bought twice. Calls $onLicence right after
     * buying one so the caller can checkpoint.
     *
     * @return array{reference:string, quota_gb:?float, used_mb:?float}
     */
    public function createMailbox(string $domain, string $localPart, string $displayName, string $password, bool $licenceOrdered, callable $onLicence, array $context = []): array;

    public function deleteMailbox(string $reference, array $context = []): void;

    public function setPassword(string $domain, string $localPart, string $password, array $context = []): void;

    public function setDisplayName(string $domain, string $localPart, string $displayName, array $context = []): void;

    /** Storage for a mailbox. @return array{quota_gb:?float, used_mb:?float}|null */
    public function usage(string $domain, string $localPart, array $context = []): ?array;

    /** Add an alias delivering into a mailbox. Returns the alias reference. */
    public function addAlias(string $domain, string $mailboxLocalPart, string $aliasLocalPart, array $context = []): string;

    public function deleteAlias(string $reference, array $context = []): void;

    /** TODO(openprovider): forwarding to an outside address is not in the documented API. */
    public function addForward(string $domain, string $localPart, string $target, array $context = []): void;

    /** TODO(openprovider): no documented suspend call — we suspend on our side only for now. */
    public function suspendMailbox(string $reference, array $context = []): void;
}
