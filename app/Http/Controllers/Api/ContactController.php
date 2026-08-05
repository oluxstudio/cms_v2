<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmissionNotification;
use App\Mail\FormSubmissionReceipt;
use App\Models\ContactSubmission;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * POST /api/sites/{siteName}/contact
     *
     * Accepts: { name, email, subject?, message }
     */
    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'subject' => ['nullable', 'string', 'max:255'],
                'message' => ['required', 'string', 'max:5000'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        ContactSubmission::create([
            'site_id' => $site->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'ip_address' => $request->ip(),
        ]);

        // CRM funnel: the sender becomes (or updates) a Contact.
        try {
            \App\Models\Contact::capture($site, $data['name'], $data['email'], null,
                'Sent a contact message'.(! empty($data['subject']) ? ": “{$data['subject']}”" : '.'));
        } catch (\Throwable $e) {
            report($e);
        }

        // Dashboard recent-activity — contact submissions bypass FormResponse,
        // so they log here (classic/block forms log via FormResponseObserver).
        try {
            \App\Services\ActivityLogger::log($site->id, 'form_response', 'responded',
                "New contact message from {$data['name']}", [
                    'description' => $data['subject'] ?: \Illuminate\Support\Str::limit($data['message'], 120),
                    'url' => '/submissions',
                    'icon' => 'response',
                    'meta' => ['email' => $data['email'], 'form_name' => 'Contact'],
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // Email BOTH parties (best-effort — mail failure never blocks the submission):
        // the owner gets the message, the visitor gets a receipt copy.
        $fields = array_filter([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
        ]);
        try {
            if ($owner = $site->user?->email) {
                Mail::to($owner)->send(new FormSubmissionNotification($site, 'contact message', $fields, url("{$site->name}/contacts")));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            Mail::to($data['email'])->send(new FormSubmissionReceipt($site, 'message', $fields));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Your message has been sent. We\'ll be in touch soon!',
        ], 201);
    }
}
