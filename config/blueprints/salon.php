<?php

/*
 * Salon & Barber blueprint — everything a fresh salon/barbershop tenant is
 * provisioned with: the hairco template pages, booking settings, a service
 * menu with deposits, staff chairs, and the appointment form.
 * Applied by App\Services\Blueprints\SalonBlueprint.
 */
return [

    // resources/templates/{key} package whose pages are scaffolded in.
    'template' => 'hairco',

    // Booking feature settings (config/features.php bookings.settings keys).
    'booking_settings' => [
        'days' => 'mon,tue,wed,thu,fri,sat',
        'open_time' => '09:00',
        'close_time' => '18:00',
        'slot_minutes' => 30,
        'lead_hours' => 2,
        'horizon_days' => 30,
    ],

    // Service menu: [name, duration_min, price_cents, deposit_pct|null]
    'services' => [
        ['Haircut', 45, 3800, null],
        ['Beard trim', 20, 1500, null],
        ['Cut & beard', 60, 4800, null],
        ['Colour', 90, 7500, 25],
        ['Kids cut', 30, 2200, null],
    ],

    // Staff / chairs (ServiceResource rows attached to every service).
    'staff' => ['Chair 1', 'Chair 2'],

    // The form booking responses route to (BookingNotifications::formFor).
    'form' => [
        'name' => 'appointment',
        'title' => 'Book an appointment',
        'fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
    ],
];
