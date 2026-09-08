<?php

namespace App\Console\Commands;

use App\Models\TemplateSubmission;
use App\Services\SubmissionPublisher;
use App\Services\TemplateExtractor;
use App\Services\TemplateLint;
use App\Services\TemplateStager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * One-shot template intake from anywhere:
 *
 *   php artisan template:import ./my-template --accept --build
 *   php artisan template:import https://github.com/you/template.git --key=mytpl
 *   php artisan template:import mytemplate.zip --accept
 *
 * Stages the app, extracts it, gates on lint errors, records the submission
 * (pending by default; --accept publishes + syncs the catalog immediately).
 */
class TemplateImport extends Command
{
    protected $signature = 'template:import
        {source : A local app directory, a .zip file, or a git URL (https/ssh)}
        {--key= : Template key (defaults to the folder/repo/zip name)}
        {--branch= : Git branch to clone}
        {--accept : Publish to the catalog immediately (skip moderator review)}
        {--build : Also build the renderer preview (needs npm; takes minutes)}
        {--replace : Overwrite an existing staging dir with the same key}';

    protected $description = 'Import a Nuxt template app from a directory, zip or git repo';

    public function handle(TemplateStager $stager, TemplateExtractor $extractor, TemplateLint $lint, SubmissionPublisher $publisher): int
    {
        $source = (string) $this->argument('source');
        $isGit = (bool) preg_match('#^(https?://|git@|ssh://|file://)#', $source);
        $key = $this->option('key')
            ?: TemplateStager::sanitizeKey($isGit
                ? Str::before(basename($source), '.git')
                : Str::before(basename($source), '.zip'));
        $replace = (bool) $this->option('replace');
        $branch = $this->option('branch') ?: null;

        // 1. Stage.
        try {
            if ($isGit) {
                $this->line("Cloning {$source}…");
                $stager->stageGit($source, $key, $branch, $replace);
            } elseif (is_file($source) && str_ends_with(strtolower($source), '.zip')) {
                $stager->stageZip($source, $key, $replace);
            } elseif (is_dir($source)) {
                $stager->stageDirectory($source, $key, $replace);
            } else {
                $this->error("Source not found: {$source}");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Staged as “{$key}”.");

        // 2. Extract + lint gate.
        $manifest = $extractor->extract($key);
        $result = $lint->analyze($manifest, rtrim(config('templates.staging_path'), '/')."/{$key}");
        $this->line("Quality score: {$result['score']}/100");
        foreach ($result['findings'] as $f) {
            $this->line(sprintf('  [%s] %s — %s', strtoupper($f['level']), $f['area'], $f['message']));
        }
        if (collect($result['findings'])->contains(fn ($f) => $f['level'] === 'error')) {
            $this->error('Lint errors — fix them and re-import (see docs/template-authoring.md).');

            return self::FAILURE;
        }

        // 3. Record the submission (+ source repo for push-updates).
        $submission = TemplateSubmission::updateOrCreate(['key' => $key], array_filter([
            'name' => $manifest['name'],
            'extraction' => $manifest,
            'repo_url' => $isGit ? $source : null,
            'repo_branch' => $branch,
            'note' => 'cli import',
        ], fn ($v) => $v !== null) + ['status' => TemplateSubmission::STATUS_PENDING]);

        if (! $this->option('accept')) {
            $this->info('Submitted for review — accept it on the Templates → Submissions page.');

            return self::SUCCESS;
        }

        // 4. Accept: publish + catalog sync (+ optional preview build).
        try {
            $publisher->publish($submission);
        } catch (\Throwable $e) {
            $this->error('Publish failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $submission->update(['status' => TemplateSubmission::STATUS_ACCEPTED, 'reviewed_at' => now()]);
        Artisan::call('templates:sync', ['--key' => $key]);
        $this->info("Published “{$key}” to the catalog.");

        if ($this->option('build')) {
            $this->line('Building the renderer preview (this takes minutes)…');
            $this->call('nuxt:preview-build', ['--template' => $key]);
        } else {
            $this->line("Next: php artisan nuxt:preview-build --template={$key}");
        }

        return self::SUCCESS;
    }
}
