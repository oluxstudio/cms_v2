<?php

namespace App\Services\TemplateUploads;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Download a GitHub repository as a .zip so it can go through the normal
 * checked upload pipeline. Only https://github.com/{owner}/{repo} is
 * accepted (the server never fetches arbitrary URLs); private repos use the
 * configured TEMPLATES_GIT_TOKEN.
 */
class GithubTemplateFetcher
{
    /** @return array{owner:string, repo:string} */
    public static function parse(string $url): array
    {
        $url = trim($url);
        if (! preg_match('#^https://github\.com/([A-Za-z0-9][A-Za-z0-9-]{0,38})/([A-Za-z0-9._-]{1,100}?)(?:\.git)?/?$#', $url, $m)) {
            throw new RuntimeException('Use a GitHub repository address like https://github.com/owner/repo.');
        }

        return ['owner' => $m[1], 'repo' => $m[2]];
    }

    /**
     * Fast pre-flight (one small API call): the repo — and branch, when given —
     * is reachable with the server's token. Throws the same messages as fetch().
     */
    public function check(string $url, ?string $branch): void
    {
        ['owner' => $owner, 'repo' => $repo] = self::parse($url);
        $branch = $this->branch($branch);
        $path = "repos/{$owner}/{$repo}".($branch ? '/branches/'.implode('/', array_map('rawurlencode', explode('/', $branch))) : '');
        $res = $this->request(20)->get("https://api.github.com/{$path}");
        if (! $res->successful()) {
            throw new RuntimeException($this->failure($res->status(), $owner, $repo));
        }
    }

    /** Save the repo's zip to $dest. */
    public function fetch(string $url, ?string $branch, string $dest): void
    {
        ['owner' => $owner, 'repo' => $repo] = self::parse($url);
        $branch = $this->branch($branch);

        $api = "https://api.github.com/repos/{$owner}/{$repo}/zipball".($branch ? '/'.implode('/', array_map('rawurlencode', explode('/', $branch))) : '');
        File::ensureDirectoryExists(dirname($dest));
        $res = $this->request(120)->sink($dest)->get($api);

        if (! $res->successful()) {
            File::delete($dest);
            throw new RuntimeException($this->failure($res->status(), $owner, $repo));
        }
        $maxBytes = (int) config('templates.uploads.max_zip_kb', 61440) * 1024;
        $size = (int) filesize($dest);
        if ($size > $maxBytes) {
            File::delete($dest);
            throw new RuntimeException('The repository is too large ('.round($size / 1048576).' MB). Leave build output and node_modules out of the repo.');
        }
    }

    private function branch(?string $branch): ?string
    {
        $branch = trim((string) $branch);
        if ($branch !== '' && ! preg_match('#^[A-Za-z0-9._/-]{1,100}$#', $branch)) {
            throw new RuntimeException('That branch name isn\'t valid.');
        }

        return $branch !== '' ? $branch : null;
    }

    private function request(int $timeout)
    {
        $req = Http::timeout($timeout)->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'olux-template-import']);
        $token = (string) config('templates.git.token');

        return $token !== '' ? $req->withToken($token) : $req;
    }

    private function failure(int $status, string $owner, string $repo): string
    {
        $token = (string) config('templates.git.token');

        // GitHub answers 404 (not 403) for private repos it won't show you.
        return match (true) {
            $status === 404 && $token === '' => 'Repository not found. If it\'s private, the server needs a GitHub token: set TEMPLATES_GIT_TOKEN to a token that can read '.$owner.'/'.$repo.'.',
            $status === 404 => 'Repository or branch not found. Check the branch name and that the server\'s GitHub token can read '.$owner.'/'.$repo.'.',
            in_array($status, [401, 403], true) => 'GitHub refused the server\'s token (HTTP '.$status.') — it may have expired or lack read access.',
            default => 'GitHub download failed (HTTP '.$status.').',
        };
    }
}
