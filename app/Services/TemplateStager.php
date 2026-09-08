<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Gets a Nuxt template app into the staging folder from wherever it lives —
 * a git repo (incl. private, via TEMPLATES_GIT_TOKEN), an uploaded .zip, or
 * a local directory. Every path lands as staging/{key}/ ready for the
 * existing scan → lint → publish pipeline.
 */
class TemplateStager
{
    // Never staged: build artefacts, VCS, AND author-machine files that may
    // carry secrets or tooling (.env*, MCP config, author sync scripts).
    private const COPY_EXCLUDE = ['node_modules', '.nuxt', '.output', '.git', '.github', '.env', '.mcp.json', 'scripts'];

    public function __construct(private TemplateSecurity $security) {}

    public static function sanitizeKey(string $raw): string
    {
        $key = Str::limit(Str::slug($raw), 48, '');
        if ($key === '' || $key === 'blank') {
            throw new RuntimeException('Pick a valid template key (letters, numbers, dashes).');
        }

        return $key;
    }

    /** Clone a repo (shallow) and stage it. Private https repos use the configured token. */
    public function stageGit(string $url, string $key, ?string $branch = null, bool $replace = false): string
    {
        if (! Process::run('git --version')->successful()) {
            throw new RuntimeException('git is not available on this server.');
        }

        $cloneUrl = $url;
        if (($token = (string) config('templates.git.token')) !== '' && str_starts_with($url, 'https://')) {
            $cloneUrl = preg_replace('#^https://#', 'https://x-access-token:'.$token.'@', $url);
        }

        $tmp = storage_path('app/tmp-import-'.uniqid());
        try {
            $cmd = array_filter(['git', 'clone', '--depth', '1', $branch ? '--branch' : null, $branch, $cloneUrl, $tmp]);
            $result = Process::timeout(300)->run($cmd);
            if (! $result->successful()) {
                // Never echo the tokenised URL back.
                $err = str_replace($cloneUrl, $url, $result->errorOutput());
                throw new RuntimeException("Could not clone {$url}: ".Str::limit(trim($err), 300)
                    .' (private repo? set TEMPLATES_GIT_TOKEN, or use an SSH URL with a deploy key).');
            }

            return $this->stageDirectory($tmp, $key, $replace);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /** Unpack an app .zip into staging — entry by entry, never extractTo(). */
    public function stageZip(string $zipPath, string $key, bool $replace = false): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the .zip file.');
        }

        try {
            $this->security->inspectAppZip($zip);
            $prefix = $this->wrappingFolder($zip);
            $target = $this->prepareTarget($key, $replace);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $rel = $prefix !== null ? Str::after($name, $prefix) : $name;
                if ($rel === '' || $rel === $name && $prefix !== null) {
                    continue; // outside the wrapper
                }
                $data = $zip->getFromIndex($i);
                if ($data === false) {
                    continue;
                }
                $dest = $target.'/'.$rel;
                File::ensureDirectoryExists(dirname($dest));
                File::put($dest, $data);
                if (is_link($dest)) { // belt and braces
                    File::delete($dest);
                    throw new RuntimeException("Symbolic link rejected: {$rel}");
                }
            }

            return $this->assertContract($target, $key);
        } finally {
            $zip->close();
        }
    }

    /** Copy a local app directory into staging (source dirs like a git checkout). */
    public function stageDirectory(string $sourceDir, string $key, bool $replace = false): string
    {
        if (! File::isDirectory($sourceDir)) {
            throw new RuntimeException("Not a directory: {$sourceDir}");
        }
        $target = $this->prepareTarget($key, $replace);
        $this->copyTree($sourceDir, $target);

        return $this->assertContract($target, $key);
    }

    // ── internals ────────────────────────────────────────────────────

    private function prepareTarget(string $key, bool $replace): string
    {
        $staging = rtrim((string) config('templates.staging_path'), '/');
        $target = "{$staging}/{$key}";
        if (File::isDirectory($target)) {
            if (! $replace) {
                throw new RuntimeException("“{$key}” already exists in staging — tick Replace (or pass --replace) to overwrite it.");
            }
            // Replace CONTENTS in place, keeping dev-only files (node_modules,
            // .env) and the directory inode itself — the staging folder doubles
            // as the author's local dev app, and a full delete forces a
            // reinstall and breaks any shell sitting in the folder.
            foreach (File::directories($target) as $dir) {
                if (basename($dir) !== 'node_modules') {
                    File::deleteDirectory($dir);
                }
            }
            foreach (File::files($target, true) as $file) {
                if (! str_starts_with($file->getFilename(), '.env')) {
                    File::delete($file->getPathname());
                }
            }
        }
        if (! File::isDirectory($staging) || (! @File::ensureDirectoryExists($target) && ! File::isDirectory($target))) {
            throw new RuntimeException("Cannot write to the staging folder ({$staging}) — check that the ../templates bind mount exists and is writable by the app user.");
        }

        return $target;
    }

    private function copyTree(string $from, string $to): void
    {
        foreach (File::files($from, true) as $file) {
            $rel = Str::after($file->getPathname(), rtrim($from, '/').'/');
            $top = explode('/', $rel)[0];
            if (in_array($top, self::COPY_EXCLUDE, true) || str_starts_with($top, '.env')) {
                continue;
            }
            if ($file->isLink()) {
                continue;
            }
            $dest = $to.'/'.$rel;
            File::ensureDirectoryExists(dirname($dest));
            File::copy($file->getPathname(), $dest);
        }
        // File::files(hidden) misses nested dirs on some setups — walk dirs explicitly.
        foreach (File::directories($from) as $dir) {
            $name = basename($dir);
            if (in_array($name, self::COPY_EXCLUDE, true) || is_link($dir)) {
                continue;
            }
            $this->copyTree($dir, $to.'/'.$name);
        }
    }

    /** A single top-level folder wrapping everything (GitHub archive style) → its prefix. */
    private function wrappingFolder(ZipArchive $zip): ?string
    {
        $tops = [];
        $rootFiles = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (! str_contains(rtrim($name, '/'), '/')) {
                if (! str_ends_with($name, '/')) {
                    $rootFiles = true;
                }
                if (str_ends_with($name, '/')) {
                    $tops[rtrim($name, '/')] = true;
                }
            } else {
                $tops[explode('/', $name)[0]] = true;
            }
        }

        return (! $rootFiles && count($tops) === 1) ? array_key_first($tops).'/' : null;
    }

    private function assertContract(string $target, string $key): string
    {
        if (! File::exists("{$target}/package.json") || ! File::isDirectory("{$target}/app/pages")) {
            File::deleteDirectory($target);
            throw new RuntimeException("“{$key}” is not a Nuxt template app — it must contain package.json and app/pages/ at its root (see docs/template-authoring.md).");
        }

        return $target;
    }
}
