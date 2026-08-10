<?php

/*
|--------------------------------------------------------------------------
| Form delivery channels
|--------------------------------------------------------------------------
|
| The single source of truth for the ways a form submission can be delivered.
| Each form stores which of these it has enabled (forms.delivery json), and
| App\Services\FormDelivery dispatches to the ones that are both enabled AND
| `implemented`. Email is live today; SMS/WhatsApp are surfaced in the admin
| UI as "coming soon" and skipped by the dispatcher until wired to a provider.
|
*/

return [

    'channels' => [

        'email' => [
            'label' => 'Email',
            'description' => 'Send a receipt to the visitor and an alert to the site admin.',
            'icon' => 'envelope',
            'implemented' => true,
        ],

        'sms' => [
            'label' => 'SMS',
            'description' => 'Text the admin (and optionally the visitor) when a form is submitted.',
            'icon' => 'device-phone-mobile',
            'implemented' => false,
        ],

        'whatsapp' => [
            'label' => 'WhatsApp',
            'description' => 'Notify via WhatsApp Business when a form is submitted.',
            'icon' => 'chat-bubble-left-right',
            'implemented' => false,
        ],

    ],

];
