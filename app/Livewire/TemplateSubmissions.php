<?php

namespace App\Livewire;

use App\Models\TemplateSubmission;
use App\Services\SubmissionPublisher;
use App\Services\TemplateExtractor;
use App\Services\TemplateRepoIngest;
use App\Services\TemplateStager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Moderator review of template-app submissions from the staging folder
 * (config('templates.staging_path')): Scan → extraction summary cards →
 * Preview build → Accept (publish to marketplace) / Reject (with note).
 */
class TemplateSubmissions extends Component
{
    use WithFileUploads;

    /** Intake card state: zip upload + import-from-repo. */
    public $appZip = null;

    public bool $confirmReplace = false;

    public string $repoUrl = '';

    public string $repoBranch = '';

    public string $repoKey = '';

    public string $intakeError = '';

    public string $rejectNote = '';

    public ?string $rejectingId = null;

    private function isModerator(): bool
    {
        return auth()->user()
            && in_array(auth()->user()->email, (array) config('templates.moderators'), true);
    }

    /** Upload a zipped Nuxt app straight into staging, then scan it. */
    public function uploadApp(): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $this->intakeError = '';
        $this->validate(['appZip' => ['required', 'file', 'max:61440']]); // 60 MB

        try {
            $key = TemplateStager::sanitizeKey(
                pathinfo($this->appZip->getClientOriginalName(), PATHINFO_FILENAME));
            app(TemplateStager::class)->stageZip($this->appZip->getRealPath(), $key, $this->confirmReplace);
        } catch (\Throwable $e) {
            $this->intakeError = $e->getMessage();

            return;
        }
        $this->reset('appZip', 'confirmReplace');
        $this->scan();
    }

    /** Clone a (private) repo into staging and scan it — the repo-first intake. */
    public function importRepo(): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $this->intakeError = '';
        $this->validate(['repoUrl' => ['required', 'string', 'max:500']]);
        if (! preg_match('#^(https?://|git@)#', $this->repoUrl)) {
            $this->intakeError = 'Enter a git URL (https://… or git@…).';

            return;
        }

        try {
            $key = TemplateStager::sanitizeKey(
                $this->repoKey ?: Str::before(basename($this->repoUrl), '.git'));
            app(TemplateRepoIngest::class)
                ->fromRepo(trim($this->repoUrl), $key, trim($this->repoBranch) ?: null);
        } catch (\Throwable $e) {
            $this->intakeError = $e->getMessage();

            return;
        }
        $this->reset('repoUrl', 'repoBranch', 'repoKey');
        $this->dispatch('toast', level: 'success', title: 'Imported', message: "“{$key}” staged from its repository — review it below.");
    }

    /** Re-pull a submission from its source repo (republishes if already accepted). */
    public function pullLatest(string $id): void
    {
        if (! $this->isModerator()) {
            return;
        }
        $sub = TemplateSubmission::findOrFail($id);
        try {
            app(TemplateRepoIngest::class)->pull($sub);
            $this->dispatch('toast', level: 'success', title: 'Updated', message: "“{$sub->key}” re-imported from {$sub->repo_url}.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', level: 'error', title: 'Pull failed', message: $e->getMessage());
        }
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
    public function buildPreview(string $id): void
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
    public function accept(string $id): void
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
            'status' => TemplateSubmission::STATUS_ACCEPTED,
            'note' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        // Background: build the renderer app for previews, then sync the
        // catalog so the accepted template is browsable without a manual step.
        dispatch(function () use ($sub) {
            Artisan::call('nuxt:preview-build', ['--template' => $sub->key]);
            Artisan::call('templates:sync');
        });
        $this->dispatch('toast', level: 'success', title: 'Accepted', message: "“{$sub->name}” published — renderer build and catalog sync queued.");
    }

    public function startReject(string $id): void
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
            'status' => TemplateSubmission::STATUS_REJECTED,
            'note' => Str::limit(trim($this->rejectNote), 500),
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
            'subs' => $subs,
            'isModerator' => $this->isModerator(),
        ]);
    }
}
