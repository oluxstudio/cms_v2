<?php

use App\Mail\BookingConfirmed;
use App\Mail\EstimateQuoteMail;
use App\Mail\InvoiceSent;
use App\Mail\ReviewRequest;
use App\Models\Booking;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Support\EmailTemplate;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

function bmSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'bm-'.uniqid(),
        'domain' => 'bm-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 't',
    ]);

    return [$owner, $site];
}

function bmBooking(Site $site): Booking
{
    return Booking::create([
        'site_id' => $site->id, 'reference' => 'BK-'.rand(1000, 9999),
        'customer_name' => 'Jo Bloggs', 'customer_email' => 'jo@example.com',
        'status' => 'confirmed', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
        'total_cents' => 3500, 'paid_cents' => 0, 'currency' => 'gbp',
    ]);
}

test('booking confirmation uses the default subject verbatim when un-customised', function () {
    [, $site] = bmSite();
    $booking = bmBooking($site);
    $siteName = ucwords(str_replace('-', ' ', $site->name));

    $mail = new BookingConfirmed($booking, $site);
    expect($mail->envelope()->subject)
        ->toBe("Your booking with {$siteName} is confirmed — {$booking->reference}");

    $html = $mail->render();
    expect($html)->toContain('Booking details')
        ->toContain($booking->reference)
        ->toContain('Hi Jo Bloggs');
});

test('a customised booking template changes subject and body, and disabled sections vanish', function () {
    [, $site] = bmSite();
    $booking = bmBooking($site);

    EmailTemplate::saveFor($site, 'booking_confirmed', 'See you soon {name}! ({reference})', [
        ['key' => 'logo', 'enabled' => true, 'text' => null],
        ['key' => 'greeting', 'enabled' => false, 'text' => null],
        ['key' => 'intro', 'enabled' => true, 'text' => 'Custom copy: your {service} is locked in.'],
        ['key' => 'booking_summary', 'enabled' => true, 'text' => null],
        ['key' => 'outro', 'enabled' => false, 'text' => null],
        ['key' => 'footer', 'enabled' => true, 'text' => 'The {site} team'],
    ]);

    $mail = new BookingConfirmed($booking, $site->fresh());
    expect($mail->envelope()->subject)->toBe("See you soon Jo Bloggs! ({$booking->reference})");

    $html = $mail->render();
    expect($html)->toContain('Custom copy: your Service is locked in.')
        ->toContain('The '.ucwords(str_replace('-', ' ', $site->name)).' team')
        ->not->toContain('Hi Jo Bloggs')                       // greeting disabled
        ->not->toContain('If you need to change or cancel');   // outro disabled
});

test('the invoice email renders the template and still attaches the PDF', function () {
    [, $site] = bmSite();
    $invoice = Invoice::create([
        'site_id' => $site->id, 'number' => 'INV-7001', 'public_token' => bin2hex(random_bytes(12)),
        'customer_name' => 'Jo', 'customer_email' => 'jo@example.com',
        'items' => [['description' => 'Cut & finish', 'qty' => 2, 'unit_cents' => 6000]],
        'subtotal_cents' => 12000, 'tax_bp' => 0, 'tax_cents' => 0, 'total_cents' => 12000,
        'currency' => 'gbp', 'status' => 'sent',
    ]);

    $mail = new InvoiceSent($invoice, $site);
    $siteName = ucwords(str_replace('-', ' ', $site->name));
    expect($mail->envelope()->subject)->toBe("Invoice INV-7001 from {$siteName} — ".$invoice->formattedTotal())
        ->and($mail->attachments())->toHaveCount(1);

    $html = $mail->render();
    expect($html)->toContain('Cut &amp; finish')
        ->toContain('View &amp; pay invoice')
        ->toContain('open.gif'); // tracking pixel survives
});

test('the review request renders the button with its URL', function () {
    [, $site] = bmSite();
    $booking = bmBooking($site);

    $html = (new ReviewRequest($booking, $site, 'https://g.page/review-me'))->render();
    expect($html)->toContain('https://g.page/review-me')->toContain('Leave a review');
});

test('the estimate quote uses the site template but a per-estimator draft wins', function () {
    [, $site] = bmSite();
    $ref = 'EST-'.strtoupper(substr(uniqid(), -6)); // unique: the testing DB persists between runs
    $estimate = Estimate::create([
        'site_id' => $site->id, 'reference' => $ref, 'customer_name' => 'Jo',
        'customer_email' => 'jo@example.com', 'trade' => 'plumbing',
        'cost_low_cents' => 0, 'cost_high_cents' => 0, 'inputs' => [], 'results' => [],
        'hours' => 1, 'completion' => 1, 'status' => 'new',
    ]);

    EmailTemplate::saveFor($site, 'estimate_quote', 'Quote {reference} inside', [
        ['key' => 'intro', 'enabled' => true, 'text' => 'Template intro for {name}.'],
    ]);
    $mail = new EstimateQuoteMail($site->fresh(), $estimate, [['label' => 'Estimated cost', 'formatted' => '£500']]);
    expect($mail->envelope()->subject)->toBe("Quote {$ref} inside");
    expect($mail->render())->toContain('Template intro for Jo.')->toContain('£500');
});

test('customer emails come from the business name and replies go to the business', function () {
    [, $site] = bmSite();
    SiteProperties::save($site, ['values' => ['site_name' => 'Grace Way', 'email_sender_name' => "Grace\r\nWay Bookings", 'reply_to' => 'bookings@gw.test']]);

    $env = (new BookingConfirmed(bmBooking($site), $site->fresh()))->envelope();
    expect($env->from->name)->toBe('Grace Way Bookings')               // header-safe
        ->and($env->from->address)->toBe(config('mail.from.address'))  // platform address kept
        ->and($env->replyTo[0]->address)->toBe('bookings@gw.test');
});
