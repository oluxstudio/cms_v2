<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Polls module public API — client sites list open polls and cast votes.
 *
 *   GET  /api/sites/{site}/polls              → open polls + live results
 *   POST /api/sites/{site}/polls/{slug}/vote  → cast a vote, returns results
 *
 * One vote per visitor per poll (voter_hash = ip + UA + optional client
 * fingerprint) unless the poll allows multiple choices — then one per option.
 */
class PollApiController extends Controller
{
    private function site(string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('polls'), 404);

        return $site;
    }

    public function index(string $siteName): JsonResponse
    {
        $site = $this->site($siteName);

        $polls = Poll::where('site_id', $site->id)->orderByDesc('created_at')->get()
            ->filter(fn (Poll $p) => $p->acceptsVotes())->values()
            ->map(fn (Poll $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'question' => $p->question,
                'multiple' => $p->multiple,
                'ends_at' => $p->ends_at?->toIso8601String(),
                'total_votes' => $p->votes()->count(),
                'total_voters' => $p->voterCount(),
                'options' => $p->results(),
            ]);

        return response()->json(['polls' => $polls]);
    }

    public function vote(string $siteName, string $slug, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $poll = Poll::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();
        abort_unless($poll->acceptsVotes(), 422, 'This poll is closed.');

        $data = $request->validate([
            'option' => ['required_without:options', 'string'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*' => ['string'],
            'fingerprint' => ['nullable', 'string', 'max:120'],
        ]);

        $picked = $poll->multiple
            ? array_values(array_unique((array) ($data['options'] ?? array_filter([$data['option'] ?? null]))))
            : [(string) ($data['option'] ?? ($data['options'][0] ?? ''))];
        $optionIds = $poll->options()->whereIn('id', $picked)->pluck('id');
        abort_if($optionIds->isEmpty(), 422, 'Pick at least one option.');

        $hash = hash('sha256', implode('|', [
            $poll->id, $request->ip(), (string) $request->userAgent(), (string) ($data['fingerprint'] ?? ''),
        ]));

        // Single-choice polls: one vote per visitor, full stop.
        if (! $poll->multiple && $poll->votes()->where('voter_hash', $hash)->exists()) {
            return response()->json(['message' => 'You have already voted on this poll.', 'options' => $poll->results()], 409);
        }

        foreach ($optionIds as $optionId) {
            PollVote::firstOrCreate(
                ['poll_id' => $poll->id, 'poll_option_id' => $optionId, 'voter_hash' => $hash],
                ['site_id' => $site->id, 'ip_address' => $request->ip(), 'created_at' => now()],
            );
        }

        return response()->json(['message' => 'Thanks for voting!', 'options' => $poll->results(), 'total_votes' => $poll->votes()->count(), 'total_voters' => $poll->voterCount()], 201);
    }
}
