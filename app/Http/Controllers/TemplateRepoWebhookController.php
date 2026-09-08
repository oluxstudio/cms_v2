<?php

namespace App\Http\Controllers;

use App\Models\TemplateSubmission;
use App\Services\TemplateRepoIngest;
use Illuminate\Http\Request;

/**
 * Push-to-repo → template updates in the CMS. GitHub-compatible webhook
 * (X-Hub-Signature-256 HMAC of the raw body with TEMPLATES_REPO_WEBHOOK_SECRET).
 * Only repos already known to a submission are ingested; already-accepted
 * templates republish automatically, new ones wait for moderator review.
 */
class TemplateRepoWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('templates.git.webhook_secret');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook not configured.'], 400);
        }
        $signature = (string) ($request->header('X-Hub-Signature-256') ?: $request->header('X-Olux-Signature'));
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($expected, str_starts_with($signature, 'sha256=') ? $signature : 'sha256='.$signature)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $repo = (array) $request->input('repository', []);
        $candidates = array_filter([
            $repo['clone_url'] ?? null, $repo['html_url'] ?? null, $repo['ssh_url'] ?? null,
            $request->input('repo_url'),
        ]);
        $submission = TemplateSubmission::whereNotNull('repo_url')
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $url) {
                    $q->orWhere('repo_url', $url)->orWhere('repo_url', preg_replace('/\.git$/', '', $url))
                        ->orWhere('repo_url', $url.'.git');
                }
            })->first();

        if (! $submission) {
            return response()->json(['message' => 'No template tracks this repository.'], 202);
        }

        dispatch(function () use ($submission) {
            try {
                app(TemplateRepoIngest::class)->pull($submission);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return response()->json(['queued' => $submission->key]);
    }
}
