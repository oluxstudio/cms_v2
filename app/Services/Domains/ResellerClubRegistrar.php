<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ResellerClub (LogicBoxes) HTTP API. Needs a reseller account with a funded
 * balance; set RESELLERCLUB_SANDBOX=false for the live endpoint.
 *
 * Docs: https://manage.resellerclub.com/kb/answer/744
 */
class ResellerClubRegistrar implements Registrar
{
    private function base(): string
    {
        return config('domains.resellerclub.sandbox')
            ? 'https://test.httpapi.com/api/'
            : 'https://httpapi.com/api/';
    }

    private function auth(): array
    {
        return [
            'auth-userid' => config('domains.resellerclub.user_id'),
            'api-key' => config('domains.resellerclub.api_key'),
        ];
    }

    private function call(string $method, string $path, array $params): array
    {
        $req = Http::timeout(20)->acceptJson();
        $url = $this->base().$path.'.json';
        $res = $method === 'GET'
            ? $req->get($url, $this->auth() + $params)
            : $req->asForm()->post($url, $this->auth() + $params);

        $json = $res->json();
        if (! $res->ok() || (is_array($json) && ($json['status'] ?? '') === 'ERROR')) {
            throw new RuntimeException('ResellerClub: '.($json['message'] ?? $res->body()));
        }

        return is_array($json) ? $json : ['value' => $json];
    }

    public function available(string $label, array $tlds): array
    {
        $params = ['domain-name' => $label];
        foreach ($tlds as $i => $tld) {
            $params["tlds[$i]"] = $tld;
        }
        $res = $this->call('GET', 'domains/available', $params);

        $out = [];
        foreach ($tlds as $tld) {
            $out["{$label}.{$tld}"] = ($res["{$label}.{$tld}"]['status'] ?? '') === 'available';
        }

        return $out;
    }

    public function register(string $domain, array $contact, int $years): string
    {
        $customer = (string) config('domains.resellerclub.customer_id');
        $contactId = $this->contactId($customer, $contact);

        $res = $this->call('POST', 'domains/register', [
            'domain-name' => $domain,
            'years' => $years,
            'ns' => 'ns1.resellerclub.com', // placeholder — pointAt() switches to our records
            'customer-id' => $customer,
            'reg-contact-id' => $contactId,
            'admin-contact-id' => $contactId,
            'tech-contact-id' => $contactId,
            'billing-contact-id' => $contactId,
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => 'true',
        ]);

        return (string) ($res['entityid'] ?? $res['value'] ?? '');
    }

    public function pointAt(string $domain, string $target): void
    {
        $orderId = (string) $this->call('GET', 'domains/orderid', ['domain-name' => $domain])['value'];
        $this->call('POST', 'dns/activate', ['order-id' => $orderId]);

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            $this->call('POST', 'dns/manage/add-ipv4-record', ['domain-name' => $domain, 'value' => $target, 'host' => '', 'ttl' => 3600]);
        } else {
            $this->call('POST', 'dns/manage/add-cname-record', ['domain-name' => $domain, 'value' => $target, 'host' => '', 'ttl' => 3600]);
        }
        $this->call('POST', 'dns/manage/add-cname-record', ['domain-name' => $domain, 'value' => $domain, 'host' => 'www', 'ttl' => 3600]);
    }

    private function contactId(string $customer, array $c): string
    {
        $res = $this->call('POST', 'contacts/add', [
            'name' => $c['name'], 'company' => $c['company'] ?: $c['name'], 'email' => $c['email'],
            'address-line-1' => $c['address'], 'city' => $c['city'], 'country' => $c['country'],
            'zipcode' => $c['zip'], 'phone-cc' => '44', 'phone' => preg_replace('/\D/', '', $c['phone']),
            'customer-id' => $customer, 'type' => 'Contact',
        ]);

        return (string) ($res['value'] ?? '');
    }
}
