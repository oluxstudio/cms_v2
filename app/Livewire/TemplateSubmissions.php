<?php

namespace App\Livewire;

use App\Models\TemplateSubmission;
use App\Services\SubmissionPublisher;
use App\Services\TemplateExtractor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Moderator review of template-app submissions from the staging folder
 * (config('templates.staging_path')): Scan → extraction summary cards →
 * Preview build → Accept (publish to marketplace) / Reject (with note).
 */
class TemplateSubmissions extends Component
{
    public string $rejectNote = '';
    public ?int $rejectingId = null;

    private function isModerator(): bool
    {
        return auth()->user()
            && in_array(auth()->user()->email, (array) config('templates.moderators'), true);
    }

    /** Scan the staging folder: upsert a pending submission per Nuxt app found. */
    public function scan(): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $staging = rtrim(config('templates.staging_path'), '/');
        if (! File::isDirectory($staging)) {
            $this->dispatch('toast', level: 'error', title: 'Staging missing', message: 'Staging folder not mounted: '.$staging);

            return;
        }

        $found = 0;
        foreach (File::directories($staging) as $dir) {
            $key = basename($dir);
            // Only full Nuxt apps qualify (split-file packages go through the ZIP importer).
            if (! File::exists("$dir/package.json") || ! File::isDirectory("$dir/app/pages")) {
                continue;
            }
            try {
                $manifest = app(TemplateExtractor::class)->extract($key);
            } catch (\Throwable $e) {
                $this->dispatch('toast', level: 'error', title: "Extract failed: {$key}", message: $e->getMessage());

                continue;
            }
            TemplateSubmission::updateOrCreate(
                ['key' => $key],
                ['name' => $manifest['name'], 'extraction' => $manifest]
                    + (TemplateSubmission::where('key', $key)->value('status') === TemplateSubmission::STATUS_ACCEPTED
                        ? [] : ['status' => TemplateSubmission::STATUS_PENDING]),
            );
            $found++;
        }

        $this->dispatch('toast', level: 'success', title: 'Scan complete', message: "{$found} template app(s) extracted.");
    }

    /** Build the staging app into /nuxt-preview/_staging/{key}/ (queued — takes minutes). */
    public function buildPreview(int $id): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $sub = TemplateSubmission::findOrFail($id);
        $path = $sub->stagingPath();
        dispatch(function () use ($path) {
            Artisan::call('nuxt:preview-build', ['--path' => $path]);
        });
        $this->dispatch('toast', level: 'success', title: 'Preview building', message: "“{$sub->name}” is building in the background — the Preview button activates when it's done.");
    }

    /** Whether a finished staging preview exists for a submission. */
    public function previewUrl(TemplateSubmission $sub): ?string
    {
        return File::exists(public_path("nuxt-preview/_staging/{$sub->key}/index.html"))
            ? url("/nuxt-preview/_staging/{$sub->key}/")
            : null;
    }

    /** Accept: publish app + package to the marketplace. */
    public function accept(int $id): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $sub = TemplateSubmission::findOrFail($id);
        try {
            app(SubmissionPublisher::class)->publish($sub);
        } catch (\Throwable $e) {
            $this->dispatch('toast', level: 'error', title: 'Publish failed', message: $e->getMessage());

            return;
        }
        $sub->update([
            'status'      => TemplateSubmission::STATUS_ACCEPTED,
            'note'        => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        // Build the published renderer app in the background so site previews work.
        dispatch(function () use ($sub) {
            Artisan::call('nuxt:preview-build', ['--template' => $sub->key]);
        });
        $this->dispatch('toast', level: 'success', title: 'Accepted', message: "“{$sub->name}” published to the marketplace. Renderer build queued.");
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectNote = '';
    }

    public function reject(): void
    {
        if (! $this->isModerator() || ! $this->rejectingId) {
            return;
        }
        $sub = TemplateSubmission::findOrFail($this->rejectingId);
        $sub->update([
            'status'      => TemplateSubmission::STATUS_REJECTED,
            'note'        => Str::limit(trim($this->rejectNote), 500),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $this->rejectingId = null;
        $this->dispatch('toast', level: 'success', title: 'Rejected', message: "“{$sub->name}” was rejected.");
    }

    public function render()
    {
        $subs = $this->isModerator()
            ? TemplateSubmission::orderByRaw("field(status, 'pending', 'rejected', 'accepted')")->orderBy('name')->get()
            : collect();

        return view('livewire.template-submissions', [
            'subs'        => $subs,
            'isModerator' => $this->isModerator(),
        ]);
    }
}
