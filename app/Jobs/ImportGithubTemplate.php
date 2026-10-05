<?php

namespace App\Jobs;

use App\Models\TemplateUpload;
use App\Services\TemplateUploads\GithubTemplateFetcher;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Download a GitHub repo for a queued upload, then hand it to the normal upload pipeline. */
class ImportGithubTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public string $uploadId, public string $repoUrl, public ?string $branch = null) {}

    public function handle(GithubTemplateFetcher $github, TemplateUploadPipeline $pipeline): void
    {
        $upload = TemplateUpload::find($this->uploadId);
        if (! $upload || ! $upload->inProgress()) {
            return;
        }
        try {
            $github->fetch($this->repoUrl, $this->branch, $upload->zipPath());
        } catch (Throwable $e) {
            report($e);
            $pipeline->fail($upload, $e); // tells the uploader (alert + toast)

            return;
        }
        $upload->update(['step' => 'Waiting to start']);
        ProcessTemplateUpload::dispatch($upload->id);
    }
}
