<?php

/*
|--------------------------------------------------------------------------
| Email template catalog
|--------------------------------------------------------------------------
| Single source of truth for every ADMIN-EDITABLE outbound email (mirrors
| config/features.php + FeatureRegistry). Each entry defines the default
| subject and ordered sections, which section texts the admin may edit,
| which sections render dynamically (computed by the mailable), the
| placeholder legend shown in the editor, and sample data for the live
| preview. Per-site customisations are stored as site attributes
| email.tpl.{key}.subject / email.tpl.{key}.sections — except `receipt`,
| which keeps its legacy email.receipt_* keys.
|
| DEFAULT SUBJECTS REPLICATE THE OLD HARDCODED ENVELOPE STRINGS EXACTLY —
| an un-customised site sends the same subject it always has.
*/

return [

    'receipt' => [
        'label' => 'Submission receipt',
        'description' => 'The copy a visitor gets after any form, contact or interest submission.',
        'group' => 'Forms',
        'feature' => null,
        'subject' => 'We received your {type} — {site}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Thanks for reaching out to {site}. We've received your {type} and someone will get back to you shortly."],
            ['key' => 'summary', 'enabled' => true, 'text' => null],
            ['key' => 'footer', 'enabled' => true, 'text' => 'This message was sent by {site}. No action is needed — it\'s a copy for your records.'],
        ],
        'editable' => ['greeting', 'intro', 'footer'],
        'dynamic' => ['summary' => 'Submission summary'],
        'placeholders' => [
            '{name}' => 'Visitor name', '{site}' => 'Site name', '{type}' => 'Submission type',
            '{field:email}' => 'One submitted value', '{fields}' => 'All submitted fields',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'type' => 'message'],
            'summary' => ['name' => 'Alex', 'email' => 'alex@example.com', 'phone' => '07700 900123', 'message' => 'Looks great — please get in touch.'],
            'dynamic' => [],
        ],
    ],

    'booking_confirmed' => [
        'label' => 'Booking confirmation',
        'description' => 'Sent when a booking is confirmed (or received, when approval is needed).',
        'group' => 'Bookings',
        'feature' => 'bookings',
        'subject' => 'Your booking with {site} {status_verb} — {reference}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Your booking with {site} {status_verb}.\n\nReference: {reference}"],
            ['key' => 'booking_summary', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => 'If you need to change or cancel, just reply to this email and mention your reference.'],
            ['key' => 'footer', 'enabled' => true, 'text' => "Thanks,\n{site}"],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['booking_summary' => 'Booking details'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{reference}' => 'Booking reference',
            '{service}' => 'Service name', '{status_verb}' => '"is confirmed" or "was received"',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'reference' => 'BK-1042', 'service' => 'Haircut', 'status_verb' => 'is confirmed'],
            'dynamic' => ['booking_summary' => ['summary' => 'Haircut — Friday, October 3, 2026 at 10:00 AM', 'reference' => 'BK-1042', 'total' => '£35.00', 'paid' => null, 'balance' => null, 'notes' => null]],
        ],
    ],

    'booking_cancelled' => [
        'label' => 'Booking cancelled',
        'description' => 'Sent to the customer when a booking is cancelled.',
        'group' => 'Bookings',
        'feature' => 'bookings',
        'subject' => 'Your booking with {site} was cancelled — {reference}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Your booking with {site} has been cancelled.\n\nReference: {reference}"],
            ['key' => 'booking_summary', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => "If this is unexpected or you'd like to rebook, just reply to this email and mention your reference."],
            ['key' => 'footer', 'enabled' => true, 'text' => "Thanks,\n{site}"],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['booking_summary' => 'Booking details'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{reference}' => 'Booking reference', '{service}' => 'Service name',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'reference' => 'BK-1042', 'service' => 'Haircut'],
            'dynamic' => ['booking_summary' => ['summary' => 'Haircut — Friday, October 3, 2026 at 10:00 AM', 'reference' => 'BK-1042', 'total' => null, 'paid' => '£10.00', 'balance' => null, 'notes' => null]],
        ],
    ],

    'booking_reminder' => [
        'label' => 'Booking reminder',
        'description' => 'The "see you tomorrow" nudge, sent about 24 hours before a booking.',
        'group' => 'Bookings',
        'feature' => 'bookings',
        'subject' => 'Reminder: your booking with {site} — {reference}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "A quick reminder — your booking with {site} is coming up.\n\nReference: {reference}"],
            ['key' => 'booking_summary', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => 'Need to change or cancel? Just reply to this email and mention your reference.'],
            ['key' => 'footer', 'enabled' => true, 'text' => "See you soon,\n{site}"],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['booking_summary' => 'Booking details'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{reference}' => 'Booking reference', '{service}' => 'Service name',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'reference' => 'BK-1042', 'service' => 'Haircut'],
            'dynamic' => ['booking_summary' => ['summary' => 'Haircut — Friday, October 3, 2026 at 10:00 AM', 'reference' => 'BK-1042', 'total' => null, 'paid' => null, 'balance' => '£25.00', 'notes' => null]],
        ],
    ],

    'estimate_quote' => [
        'label' => 'Estimate quote',
        'description' => 'The quote a visitor receives after filling in the cost estimator.',
        'group' => 'Forms',
        'feature' => 'estimator',
        'subject' => 'Your {service} estimate {reference} from {site}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'intro', 'enabled' => true, 'text' => "Hi {name},\n\nThanks for requesting a {service} estimate from {site}. Here is what we calculated for you — reference {reference}.\n\nWe'll be in touch shortly to talk it through."],
            ['key' => 'quote_summary', 'enabled' => true, 'text' => null],
            ['key' => 'footer', 'enabled' => true, 'text' => '{site}'],
        ],
        'editable' => ['intro', 'footer'],
        'dynamic' => ['quote_summary' => 'Calculated results'],
        // Sites that customised the old single-body editor keep their copy.
        'legacy' => ['subject' => 'estimator.email_subject', 'intro' => 'estimator.email_body'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{reference}' => 'Estimate reference',
            '{service}' => 'Service / trade', '{cost}' => 'Calculated cost', '{completion}' => 'Estimated completion',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'reference' => 'EST-2091', 'service' => 'Bathroom refit', 'cost' => '£4,200 – £5,100', 'completion' => '2–3 weeks'],
            'dynamic' => ['quote_summary' => ['results' => [['label' => 'Estimated cost', 'formatted' => '£4,200 – £5,100'], ['label' => 'Completion', 'formatted' => '2–3 weeks']], 'reference' => 'EST-2091']],
        ],
    ],

    'invoice_sent' => [
        'label' => 'Invoice',
        'description' => 'The invoice email — line items, pay button, and the PDF is always attached.',
        'group' => 'Billing',
        'feature' => 'invoices',
        'subject' => 'Invoice {number} from {site} — {total}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => '{site} has sent you an invoice for {total}.'],
            ['key' => 'invoice_summary', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => 'If you have any questions, just reply to this email.'],
            ['key' => 'footer', 'enabled' => true, 'text' => "Thanks,\n{site}"],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['invoice_summary' => 'Invoice details & pay button'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{number}' => 'Invoice number',
            '{total}' => 'Invoice total', '{due}' => 'Due date',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'number' => 'INV-0042', 'total' => '£360.00', 'due' => 'October 15, 2026'],
            'dynamic' => ['invoice_summary' => ['number' => 'INV-0042', 'total' => '£360.00', 'due' => 'October 15, 2026', 'items' => [['description' => 'Cut & finish', 'qty' => 2, 'amount' => '£120.00'], ['description' => 'Colour treatment', 'qty' => 1, 'amount' => '£120.00']], 'tax' => null, 'pay_url' => '#', 'portal_url' => '#']],
        ],
    ],

    'invoice_reminder' => [
        'label' => 'Invoice reminder',
        'description' => 'A polite nudge when an invoice is due soon or overdue.',
        'group' => 'Billing',
        'feature' => 'invoices',
        'subject' => 'Reminder: invoice {number} from {site} {status_verb}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => 'Just a friendly reminder that invoice {number} for {total} {status_verb}.'],
            ['key' => 'invoice_summary', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => "If you've already paid, please disregard this email — and thank you!"],
            ['key' => 'footer', 'enabled' => true, 'text' => '{site}'],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['invoice_summary' => 'Invoice details & pay button'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{number}' => 'Invoice number',
            '{total}' => 'Invoice total', '{due}' => 'Due date', '{status_verb}' => '"is due soon" or "is overdue"',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'number' => 'INV-0042', 'total' => '£360.00', 'due' => 'October 15, 2026', 'status_verb' => 'is due soon'],
            'dynamic' => ['invoice_summary' => ['number' => 'INV-0042', 'total' => '£360.00', 'due' => 'October 15, 2026', 'items' => [], 'tax' => null, 'pay_url' => '#', 'portal_url' => '#']],
        ],
    ],

    'review_request' => [
        'label' => 'Review request',
        'description' => '"How did we do?" — sent the day after a visit, with the review link.',
        'group' => 'Marketing',
        'feature' => 'bookings',
        'subject' => 'How did we do? — {site}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Thanks for visiting {site}! We hope you loved your {service}.\n\nIf you have a spare minute, a quick review makes a huge difference to a small business like ours:"],
            ['key' => 'review_button', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => 'Thank you — and see you next time!'],
            ['key' => 'footer', 'enabled' => true, 'text' => '{site}'],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['review_button' => 'Review button'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{service}' => 'Service they had',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'service' => 'haircut'],
            'dynamic' => ['review_button' => ['url' => '#', 'label' => 'Leave a review']],
        ],
    ],

    'rebook_prompt' => [
        'label' => 'Rebook prompt',
        'description' => '"Time for your next visit?" — sent a few weeks after a customer\'s last booking.',
        'group' => 'Marketing',
        'feature' => 'bookings',
        'subject' => 'Time for your next visit? — {site}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "It's been about {weeks} weeks since your last {service} with {site} — the perfect time to book your next one."],
            ['key' => 'book_button', 'enabled' => true, 'text' => null],
            ['key' => 'footer', 'enabled' => true, 'text' => "See you soon,\n{site}"],
        ],
        'editable' => ['greeting', 'intro', 'footer'],
        'dynamic' => ['book_button' => 'Book-now button'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{service}' => 'Their usual service', '{weeks}' => 'Weeks since last visit',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'service' => 'haircut', 'weeks' => '6'],
            'dynamic' => ['book_button' => ['url' => '#', 'label' => 'Book now']],
        ],
    ],

    'order_confirmed' => [
        'label' => 'Order confirmation',
        'description' => 'The purchase receipt for store orders, with a link to track the order.',
        'group' => 'Orders',
        'feature' => 'store',
        'subject' => 'Order confirmed — thank you! · {business}',
        'sections' => [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Your payment of {total} to {business} is confirmed. Here's what you ordered:"],
            ['key' => 'order_lines', 'enabled' => true, 'text' => null],
            ['key' => 'outro', 'enabled' => true, 'text' => 'You can follow your order every step of the way — from payment to delivery — on your order page.'],
            ['key' => 'footer', 'enabled' => true, 'text' => "Thanks,\n{business}"],
        ],
        'editable' => ['greeting', 'intro', 'outro', 'footer'],
        'dynamic' => ['order_lines' => 'Order lines & track button'],
        'placeholders' => [
            '{name}' => 'Customer name', '{site}' => 'Site name', '{business}' => 'Business name',
            '{total}' => 'Order total', '{order_number}' => 'Order number',
        ],
        'sample' => [
            'ctx' => ['name' => 'Alex', 'site' => null, 'business' => null, 'total' => '£54.00', 'order_number' => '#1018'],
            'dynamic' => ['order_lines' => ['items' => [['qty' => 2, 'name' => 'Repair serum', 'amount' => '£36.00'], ['qty' => 1, 'name' => 'Curl cream', 'amount' => '£18.00']], 'total' => '£54.00', 'vat' => null, 'number' => '#1018', 'fulfilment' => null, 'shipping_address' => null, 'status_url' => '#']],
        ],
    ],
];
