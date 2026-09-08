<?php

namespace App\Services\Blueprints;

/**
 * "Salon & Barber" tenant: hairco template pages, bookings with salon hours,
 * a service menu (deposit on colour), two chairs and the appointment form.
 * Data lives in config/blueprints/salon.php.
 */
class SalonBlueprint extends Blueprint
{
    public function key(): string
    {
        return 'salon';
    }
}
