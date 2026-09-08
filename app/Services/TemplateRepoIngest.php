<?php

namespace App\Services;

use App\Models\TemplateSubmission;
use Illuminate\Support\Facades\Artisan;

/**
 * (Re-)ingest a template from its source repo: stage → extract → update the
 * submission. When the submission was already accepted, changes republish and
 * resync automatically — first-time templates always wait for moderator
 * review. Used by template:import, the Submissions "Pull latest" button and
 * the push webhook.
 */
class TemplateRepoIngest
{
    public function __construct(
        private TemplateStager $stager,
        private TemplateExtractor $extractor,
        private SubmissionPublisher $publisher,
    ) {}

    public function fromRepo(string $url, string $key, ?string $branch = null): TemplateSubmission
    {
        $this->stager->stageGit($url, $key, $branch, replace: true);

        return $this->refresh($key, $url, $branch);
    }

    public function pull(TemplateSubmission $submission): TemplateSubmission
    {
        if (! $submission->repo_url) {
            throw new \RuntimeException('This submission has no source repository.');
        }

        return $this->fromRepo($submission->repo_url, $submission->key, $submission->repo_branch);
    }

    /** Extract + upsert; republish/resync automatically only when already accepted. */
    public function refresh(string $key, ?string $repoUrl = null, ?string $branch = null): TemplateSubmission
    {
        $manifest = $this->extractor->extract($key);
        $existing = TemplateSubmission::where('key', $key)->first();
        $accepted = $existing?->status === TemplateSubmission::STATUS_ACCEPTED;

        $submission = TemplateSubmission::updateOrCreate(['key' => $key], array_filter([
            'name' => $manifest['name'],
            'extraction' => $manifest,
            'repo_url' => $repoUrl,
            'repo_branch' => $branch,
        ], fn ($v) => $v !== null) + ($accepted ? [] : ['status' => TemplateSubmission::STATUS_PENDING]));

        if ($accepted) {
            $this->publisher->publish($submission);
            Artisan::call('templates:sync', ['--key' => $key]);

            // Full deploy on push: rebuild the renderer shell and refresh
            // every site using the template — `git push` alone lands the
            // update everywhere, no CMS action needed.
            Artisan::call('nuxt:preview-build', ['--template' => $key]);
            app(TemplateInstaller::class)->refreshAppliedSites($key);
        }

        return $submission;
    }
}
