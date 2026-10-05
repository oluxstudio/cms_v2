<?php

namespace App\Services\TemplateUploads;

use App\Jobs\CollectTemplateBuild;
use App\Models\SiteTemplate;
use App\Models\Template;
use App\Models\TemplateCreator;
use App\Models\TemplateEntitlement;
use App\Models\TemplateSubmission;
use App\Models\TemplateUpload;
use App\Services\SubmissionPublisher;
use App\Services\TemplateCatalogWriter;
use App\Services\TemplateExtractor;
use App\Services\TemplateLint;
use App\Services\TemplateStager;
use App\Support\NuxtShell;
use App\Support\TaskAlerts;
use App\Support\TemplatePaths;
use App\Templates\TemplatePackage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Zip → private library template, for a client's own Nuxt app:
 * unpack + scan (the same stager/extractor/lint the moderator intake uses),
 * publish into the CMS contract (content hooks + edit agent), build, then
 * catalog it as a PRIVATE template the uploader owns.
 */
class TemplateUploadPipeline
{
    public function __construct(
        private TemplateStager $stager,
        private TemplateExtractor $extractor,
        private TemplateLint $lint,
        private SubmissionPublisher $publisher,
        private TemplateCatalogWriter $catalog,
    ) {}

    /** Unpack, scan and publish; then start the build. */
    public function scanAndPublish(TemplateUpload $upload): void
    {
        $key = $upload->key;
        $staging = TemplatePaths::uploadsRoot().'/_staging';
        File::ensureDirectoryExists($staging);
        config(['templates.staging_path' => $staging]);

        $upload->update(['status' => TemplateUpload::SCANNING, 'step' => 'Checking the files']);
        $this->stager->stageZip($upload->zipPath(), $key, true);

        $upload->update(['step' => 'Reading pages and sections']);
        $manifest = $this->extractor->extract($key);
        if (empty($manifest['pages'])) {
            throw new RuntimeException('No pages were found. The zip must contain a Nuxt app with an app/pages (or pages) folder.');
        }
        $report = $this->lint->analyze($manifest, "{$staging}/{$key}");
        $errors = collect($report['findings'])->where('level', 'error');
        if ($errors->isNotEmpty()) {
            throw new RuntimeException('The app needs changes before it can be used: '
                .$errors->pluck('message')->take(3)->implode(' · '));
        }

        $upload->update([
            'name' => $upload->name ?: ($manifest['name'] ?? null),
            'lint_score' => $report['score'],
            'warnings' => collect($report['findings'])->where('level', 'warning')->pluck('message')->unique()->take(10)->values()->all(),
            'step' => 'Connecting it to the editor',
        ]);

        $manifest['key'] = $key;
        if ($upload->name) {
            $manifest['name'] = $upload->name;
        }
        $this->publisher->publish(new TemplateSubmission(['key' => $key, 'name' => $manifest['name'] ?? $key, 'extraction' => $manifest]));

        File::deleteDirectory("{$staging}/{$key}");
        File::delete($upload->zipPath());

        $this->startBuild($upload);
    }

    private function startBuild(TemplateUpload $upload): void
    {
        $key = $upload->key;
        $appDir = TemplatePaths::appDir($key);
        NuxtShell::writeFxPlugin($appDir);
        $upload->update(['status' => TemplateUpload::BUILDING, 'step' => 'Building the site', 'build_started_at' => now()]);

        if (config('templates.uploads.builder') === 'sandbox') {
            $this->handToSandbox($upload, $appDir);
            CollectTemplateBuild::dispatch($upload->id)->delay(now()->addSeconds(20));

            return;
        }

        $this->buildLocally($appDir, TemplatePaths::shellBase($key));
        $this->finish($upload, "{$appDir}/.output/public");
    }

    /** Dev path: node is in this container. */
    private function buildLocally(string $appDir, string $base): void
    {
        $timeout = (int) config('templates.uploads.build_timeout', 1200);
        $env = array_merge(getenv() ?: [], ['NUXT_PUBLIC_DATA_MODE' => 'api', 'NUXT_APP_BASE_URL' => $base]);
        foreach ([['npm', 'install', '--no-audit', '--no-fund'], ['npx', 'nuxi', 'generate']] as $cmd) {
            $p = new Process($cmd, $appDir, $env, null, $timeout);
            $p->run();
            if (! $p->isSuccessful()) {
                throw new RuntimeException('The build failed: '.self::tail($p->getErrorOutput() ?: $p->getOutput()));
            }
        }
    }

    /** Production path: copy the app into the builder's drop folder and ask for a build. */
    private function handToSandbox(TemplateUpload $upload, string $appDir): void
    {
        $job = $this->sandboxDir($upload->key);
        File::ensureDirectoryExists(dirname($job), 0777);
        @chmod(dirname($job), 0777);
        File::deleteDirectory($job);
        File::ensureDirectoryExists("{$job}/app");
        foreach (File::directories($appDir) as $dir) {
            if (! in_array(basename($dir), ['node_modules', '.nuxt', '.output'], true)) {
                File::copyDirectory($dir, "{$job}/app/".basename($dir));
            }
        }
        foreach (File::files($appDir, true) as $file) {
            File::copy($file->getPathname(), "{$job}/app/".$file->getFilename());
        }
        // The builder runs as an unprivileged user in its own container.
        (new Process(['chmod', '-R', 'a+rwX', $job]))->run();
        File::put("{$job}/REQUEST", TemplatePaths::shellBase($upload->key));
    }

    /**
     * Poll the sandbox. Returns true when the upload reached a final state.
     */
    public function collect(TemplateUpload $upload): bool
    {
        $job = $this->sandboxDir($upload->key);
        if (File::exists("{$job}/DONE")) {
            $this->finish($upload, "{$job}/out");
            File::deleteDirectory($job);

            return true;
        }
        if (File::exists("{$job}/FAILED")) {
            $log = File::exists("{$job}/build.log") ? File::get("{$job}/build.log") : '';
            File::deleteDirectory($job);
            throw new RuntimeException('The build failed: '.self::tail($log));
        }
        $limit = (int) config('templates.uploads.build_timeout', 1200) + 600;
        if ($upload->build_started_at && $upload->build_started_at->diffInSeconds(now()) > $limit) {
            throw new RuntimeException('The build took too long and was stopped.');
        }

        return false;
    }

    /** Publish the shell, catalog it privately and put it in the uploader's library. */
    public function finish(TemplateUpload $upload, string $output): void
    {
        if (! File::exists("{$output}/index.html")) {
            throw new RuntimeException('The build finished but produced no index.html.');
        }
        $key = $upload->key;
        NuxtShell::publish($output, TemplatePaths::shellDir($key), TemplatePaths::shellBase($key));

        if ($upload->replaces_template_id && ($existing = $upload->replaces)) {
            $template = $this->finishNewVersion($upload, $existing);
        } else {
            $template = $this->finishNewTemplate($upload);
        }

        $upload->update([
            'status' => TemplateUpload::READY, 'step' => null, 'error' => null,
            'template_id' => $template->id, 'finished_at' => now(),
        ]);
        if ($upload->repo_url) {
            // Built from GitHub: remember where, for one-click "Update from GitHub".
            $template->update(['source_repo' => $upload->repo_url, 'source_branch' => $upload->repo_branch]);
        }

        if ($upload->replaces_template_id) {
            $version = $template->latestVersion?->version;
            $behind = SiteTemplate::where('template_id', $template->id)->whereNotNull('applied_at')->whereHas('site')
                ->where(fn ($q) => $q->whereNull('template_version_id')->orWhere('template_version_id', '!=', $template->latest_version_id))->count();
            TaskAlerts::done($upload->user_id, $upload->site_id, 'New version ready: '.$template->name.($version ? ' '.$version : ''),
                $behind ? $behind.' '.Str::plural('site', $behind).' can be moved to it — open the template and press "Update sites".' : 'It\'s live for new installs.',
                url('/admin/templates'), ['upload_id' => $upload->id, 'template_id' => $template->id]);
        } else {
            TaskAlerts::done($upload->user_id, $upload->site_id, 'Template ready: '.$template->name,
                $upload->for_store ? 'It\'s built and in the catalog as a draft. Publish it when you\'re happy.' : 'It\'s built and saved to your designs.',
                $this->uploadLink($upload), ['upload_id' => $upload->id, 'template_id' => $template->id]);
        }
    }

    /**
     * A new version of an existing store template: same key (the rebuilt
     * shell already replaced the old one), the next version number, and the
     * admin's catalog details (name, price, status, thumbnail…) left as they
     * are. Sites stay pinned to their version until they're updated.
     */
    private function finishNewVersion(TemplateUpload $upload, Template $existing): Template
    {
        $dir = TemplatePaths::packageDir($upload->key);
        $manifest = json_decode((string) File::get("{$dir}/template.json"), true) ?: [];
        $manifest['version'] = self::nextVersion($existing->versions()->pluck('version')->all());
        File::put("{$dir}/template.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->catalog->upsert(new TemplatePackage($dir), [
            'name' => $existing->name,
            'description' => $existing->description,
            'category' => $existing->category,
            'tags' => $existing->tags,
            'status' => $existing->status,
            'visibility' => $existing->visibility,
            'source' => $existing->source,
            'user_id' => $existing->user_id,
            'creator_id' => $existing->creator_id,
            'builtin_key' => $existing->builtin_key,
            'accent_color' => $existing->accent_color,
            'gradient_class' => $existing->gradient_class,
            'thumbnail_url' => $existing->thumbnail_url,
            'published_at' => $existing->published_at,
        ], $existing);
    }

    /** Minor bump past the highest recorded version: 1.0.0 → 1.1.0. */
    public static function nextVersion(array $versions): string
    {
        $latest = collect($versions)->filter(fn ($v) => preg_match('/^\d+(\.\d+){0,2}$/', (string) $v))
            ->sort(fn ($a, $b) => version_compare($a, $b))->last() ?? '1.0.0';
        $parts = array_map('intval', array_pad(explode('.', $latest), 3, 0));

        return $parts[0].'.'.($parts[1] + 1).'.0';
    }

    private function finishNewTemplate(TemplateUpload $upload): Template
    {
        $key = $upload->key;
        $package = new TemplatePackage(TemplatePaths::packageDir($key));
        if ($upload->for_store) {
            // Admin upload: a store draft by Olux Studio, published from /admin/templates.
            $template = $this->catalog->upsert($package, [
                'status' => 'draft',
                // Chosen in the Add template drawer: private = only assigned accounts.
                'visibility' => $upload->visibility === 'private' ? 'private' : 'public',
                'source' => 'upload',
                'user_id' => $upload->user_id,
                'creator_id' => TemplateCreator::where('slug', 'olux-studio')->value('id'),
                'builtin_key' => $key,
                'published_at' => null,
            ]);
        } else {
            $template = $this->catalog->upsert($package, [
                'status' => 'private',
                'source' => 'upload',
                'user_id' => $upload->user_id,
                'creator_id' => null,
                'builtin_key' => $key,
                'published_at' => null,
                'short_description' => 'Uploaded by you',
            ]);
            TemplateEntitlement::firstOrCreate(
                ['user_id' => $upload->user_id, 'template_id' => $template->id],
                ['source' => 'upload', 'price_paid_cents' => 0, 'purchased_at' => now()],
            );
        }

        return $template;
    }

    public function fail(TemplateUpload $upload, \Throwable $e): void
    {
        $name = $upload->name ?: $upload->original_filename ?: 'Your template';
        TaskAlerts::failed($upload->user_id, $upload->site_id, 'Template build failed: '.$name,
            $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong while processing the upload.',
            $this->uploadLink($upload), ['upload_id' => $upload->id]);
        $upload->update([
            'status' => TemplateUpload::FAILED,
            'step' => null,
            'error' => $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong while processing the upload.',
            'finished_at' => now(),
        ]);
        File::delete($upload->zipPath());
        File::deleteDirectory(TemplatePaths::uploadsRoot().'/_staging/'.$upload->key);
    }

    /** Where the uploader follows up: the admin catalog for store uploads, else the site's Design page. */
    private function uploadLink(TemplateUpload $upload): ?string
    {
        if ($upload->for_store) {
            return url('/admin/templates?tab=uploads');
        }

        return $upload->site ? url($upload->site->name.'/design') : null;
    }

    private function sandboxDir(string $key): string
    {
        return rtrim((string) config('templates.uploads.sandbox_path'), '/')."/jobs/{$key}";
    }

    /** Last meaningful lines of a build log, for the client-facing error. */
    private static function tail(string $log): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $log)), fn ($l) => $l !== ''));

        return mb_substr(implode(' ', array_slice($lines, -4)), 0, 600) ?: 'no output';
    }
}
