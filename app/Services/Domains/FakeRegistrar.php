<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Cache;

/**
 * No-account registrar for local dev + tests. Any label containing "taken"
 * (and anything registered through it earlier) is unavailable; registration
 * succeeds instantly.
 */
class FakeRegistrar implements Registrar
{
    public function available(string $label, array $tlds): array
    {
        $out = [];
        foreach ($tlds as $tld) {
            $domain = "{$label}.{$tld}";
            $out[$domain] = ! str_contains($label, 'taken') && ! Cache::has("fake-registrar:{$domain}");
        }

        return $out;
    }

    public function register(string $domain, array $contact, int $years): string
    {
        Cache::forever("fake-registrar:{$domain}", ['contact' => $contact, 'years' => $years]);

        return 'fake-'.substr(sha1($domain), 0, 10);
    }

    public function pointAt(string $domain, string $target): void
    {
        Cache::forever("fake-registrar:{$domain}:dns", $target);
    }
}
