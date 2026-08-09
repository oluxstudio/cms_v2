<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmissionNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Alert;
use App\Models\Contact;
use App\Models\Site;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * "I'm interested" — the lightest lead a client site can submit.
 *
 *   POST /api/sites/{site}/interest  { name, email, phone?, subject?, message?, source? }
 *
 * Captures/updates the CRM Contact, posts a dashboard notification and an
 * activity-feed entry, and emails the owner. Never requires a feature flag.
 */
class InterestController extends Controller
{
    public function store(string $siteName, Request $request): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'], // what they're interested in
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:80'],   // page/campaign the client site tags
        ]);

        $about = $data['subject'] ?? null;
        $touch = 'Expressed interest'.($about ? " in {$about}" : '')
            .(! empty($data['message']) ? ' — “'.Str::limit($data['message'], 140).'”' : '');

        // CRM funnel: the person becomes (or updates) a Contact.
        Contact::capture($site, $data['name'], $data['email'], $data['phone'] ?? null, $touch, array_filter([
            'source' => $data['source'] ?? null,
        ]));

        // Dashboard notification + recent-activity entry.
        try {
            Alert::create([
                'site_id' => $site->id,
                'level' => 'info',
                'type' => 'interest',
                'audience' => 'all',
                'title' => "New interest from {$data['name']}",
                'body' => $touch,
                'link' => url("{$site->name}/contacts"),
                'meta' => ['email' => $data['email'], 'source' => $data['source'] ?? null],
            ]);
            ActivityLogger::log($site->id, 'interest', 'responded',
                "{$data['name']} expressed interest".($about ? " in {$about}" : ''), [
                    'description' => $data['message'] ?? null,
                    'url' => '/contacts',
                    'meta' => ['email' => $data['email'], 'source' => $data['source'] ?? null],
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // Owner email — best-effort, never blocks the lead.
        try {
            if ($owner = $site->user?->email) {
                Mail::to($owner)->send(new FormSubmissionNotification(
                    $site,
                    $about ? "Interest: {$about}" : 'New interest',
                    array_filter([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'subject' => $about,
                        'message' => $data['message'] ?? null,
                        'source' => $data['source'] ?? null,
                    ]),
                    url("{$site->name}/contacts"),
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Visitor receipt — the branded, admin-editable acknowledgement.
        try {
            if (! empty($data['email'])) {
                Mail::to($data['email'])->send(new SubmissionReceipt(
                    $site,
                    $about ? "interest in {$about}" : 'enquiry',
                    $data['name'] ?? null,
                    array_filter([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'message' => $data['message'] ?? null,
                    ]),
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Thanks — we received your interest and will be in touch shortly.',
        ], 201);
    }
}
