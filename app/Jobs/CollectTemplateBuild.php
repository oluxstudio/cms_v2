<?php

namespace App\Jobs;

use App\Models\TemplateUpload;
use App\Services\TemplateUploads\TemplateUploadPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Poll the sandboxed builder until the upload's build finishes (short job, re-queued). */
class CollectTemplateBuild implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $uploadId) {}

    public function handle(TemplateUploadPipeline $pipeline): void
    {
        $upload = TemplateUpload::find($this->uploadId);
        if (! $upload || $upload->status !== TemplateUpload::BUILDING) {
            return;
        }
        try {
            if (! $pipeline->collect($upload)) {
                self::dispatch($this->uploadId)->delay(now()->addSeconds(15));
            }
        } catch (Throwable $e) {
            report($e);
            $pipeline->fail($upload, $e);
        }
    }
}
