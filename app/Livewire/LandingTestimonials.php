<?php

namespace App\Livewire;

use App\Models\PlatformTestimonial;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * The landing page's testimonial carousel — a lazy Livewire island, so the
 * (static, Blade) landing paints first and the testimonials stream in. Reads
 * the same query as GET /api/testimonials (PlatformTestimonial::forLanding),
 * so the page and the API always agree. $limit null = the admin's count (5).
 */
#[Lazy]
class LandingTestimonials extends Component
{
    public ?int $limit = null;

    /** Illustrative examples, shown only while nothing is published yet. */
    private const EXAMPLES = [
        ['quote' => 'Every enquiry used to live in three inboxes. Now the form, the quote and the booking are one contact with a history.', 'initials' => 'TR', 'name' => 'Tunde R.', 'role' => 'Cleaning company owner*', 'rating' => null],
        ['quote' => 'I built our price calculator myself — tapped the formula out like a calculator, and clients get their quote by email in seconds.', 'initials' => 'MK', 'name' => 'Maya K.', 'role' => 'Landscaping studio*', 'rating' => null],
        ['quote' => 'Going live was pointing our domain and clicking verify. The certificate sorted itself out — content edits show up instantly.', 'initials' => 'JD', 'name' => 'Jon D.', 'role' => 'Agency developer*', 'rating' => null],
    ];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="carousel" aria-busy="true" aria-label="Loading testimonials">
            <div class="car-view"><div class="slide"><div class="box" style="width:100%">
                <span class="tm-skel" style="width:88%"></span><span class="tm-skel" style="width:72%"></span><span class="tm-skel" style="width:40%;margin-top:18px"></span>
            </div></div></div>
        </div>
        HTML;
    }

    public function render()
    {
        $live = PlatformTestimonial::forLanding($this->limit)->map->toPublicArray()->values()->all();

        return view('livewire.landing-testimonials', [
            'slides' => $live ?: self::EXAMPLES,
            'illustrative' => $live === [],
        ]);
    }
}
