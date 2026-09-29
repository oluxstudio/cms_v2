<?php

namespace App\Jobs;

use App\Models\TemplateUpload;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Scan + publish a client's uploaded Nuxt app, then start its build. */
class ProcessTemplateUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Local builds run inside this job; sandbox builds only hand off. */
    public int $timeout = 1500;

    public function __construct(public string $uploadId) {}

    public function handle(TemplateUploadPipeline $pipeline): void
    {
        $upload = TemplateUpload::find($this->uploadId);
        if (! $upload || ! $upload->inProgress()) {
            return;
        }
        try {
            $pipeline->scanAndPublish($upload);
        } catch (Throwable $e) {
            report($e);
            $pipeline->fail($upload, $e);
        }
    }
}
