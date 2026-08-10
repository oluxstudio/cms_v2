<?php

namespace App\Services;

use GeoIp2\Database\Reader;

/**
 * IP → approximate location via a local MaxMind GeoLite2-City database.
 *
 * Fully in-house: no external calls, no rate limits. When the .mmdb file is not
 * installed (or the IP is private/unknown) every field comes back null and the
 * caller still records the visit — geo is simply blank.
 */
class GeoLocator
{
    private ?Reader $reader = null;

    private bool $attempted = false;

    /** @return array{country_code:?string,country:?string,region:?string,city:?string,latitude:?float,longitude:?float} */
    public function locate(?string $ip): array
    {
        $blank = ['country_code' => null, 'country' => null, 'region' => null, 'city' => null, 'latitude' => null, 'longitude' => null];

        if (! $ip || ! $this->reader()) {
            return $blank;
        }

        try {
            $r = $this->reader()->city($ip);

            return [
                'country_code' => $r->country->isoCode,
                'country' => $r->country->name,
                'region' => $r->mostSpecificSubdivision->name,
                'city' => $r->city->name,
                'latitude' => $r->location->latitude,
                'longitude' => $r->location->longitude,
            ];
        } catch (\Throwable $e) {
            // Unknown/private IP, or a malformed DB — degrade to blank geo.
            return $blank;
        }
    }

    private function reader(): ?Reader
    {
        if (! $this->attempted) {
            $this->attempted = true;
            $path = config('analytics.geoip_db');
            if ($path && is_file($path)) {
                try {
                    $this->reader = new Reader($path);
                } catch (\Throwable $e) {
                    $this->reader = null;
                }
            }
        }

        return $this->reader;
    }
}
