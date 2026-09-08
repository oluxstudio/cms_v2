<?php

namespace App\Services\Domains;

/**
 * Registrar API seam. One implementation per provider; the purchase flow
 * (DomainPurchase) never talks to a registrar directly.
 */
interface Registrar
{
    /**
     * Availability of one label across TLDs.
     *
     * @param  list<string>  $tlds
     * @return array<string,bool> full domain → available
     */
    public function available(string $label, array $tlds): array;

    /**
     * Register the domain for $years and return the registrar's order/ref id.
     *
     * @param  array<string,string>  $contact  name,email,company,address,city,zip,country,phone
     */
    public function register(string $domain, array $contact, int $years): string;

    /** Make the apex A record + www CNAME point at the platform. */
    public function pointAt(string $domain, string $target): void;
}
