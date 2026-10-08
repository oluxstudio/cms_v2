<?php

namespace App\Livewire;

use App\Models\PlatformTestimonial;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Public "share your experience" form (/testimonials/new). Anyone can write
 * a testimonial about Olux; it is saved PENDING and appears on the landing
 * page only after a super admin publishes it (Admin › Testimonials).
 * Honeypot + 3 submissions per hour per visitor keep spam out.
 */
class TestimonialSubmitPage extends Component
{
    public string $name = '';

    public string $role = '';

    public string $quote = '';

    public string $rating = '5';

    public string $email = '';

    /** Honeypot — real people never fill this hidden field. */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'string', 'max:120'],
            'quote' => ['required', 'string', 'min:20', 'max:600'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'email' => ['nullable', 'email', 'max:160'],
        ], [
            'quote.min' => 'Tell us a little more — at least 20 characters.',
        ], ['quote' => 'testimonial']);

        if ($this->website !== '') {
            Log::info('Testimonial honeypot tripped — dropped.', ['ip' => request()->ip()]);
            $this->sent = true; // look successful to the bot

            return;
        }

        $key = 'testimonial-submit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('quote', 'Thanks — we already have your testimonial. Please try again later.');

            return;
        }
        RateLimiter::hit($key, 3600);

        PlatformTestimonial::create([
            'name' => trim($data['name']),
            'role' => trim((string) $data['role']) ?: null,
            'quote' => trim($data['quote']),
            'rating' => $data['rating'] === '' || $data['rating'] === null ? null : (int) $data['rating'],
            'email' => trim((string) $data['email']) ?: null,
            'status' => 'pending',
            'source' => 'public',
            'ip_address' => request()->ip(),
        ]);
        $this->reset('name', 'role', 'quote', 'email');
        $this->rating = '5';
        $this->sent = true;
    }

    public function writeAnother(): void
    {
        $this->sent = false;
    }

    public function render()
    {
        return view('livewire.testimonial-submit-page', [
            'published' => PlatformTestimonial::forLanding(),
        ]);
    }
}
