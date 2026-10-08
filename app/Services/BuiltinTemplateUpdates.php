<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Templates\TemplateContract;
use App\Templates\TemplateRegistry;
use Illuminate\Support\Facades\File;

/**
 * Built-in templates ship INSIDE the CMS repo: their package
 * (resources/templates/{key}) and their built site (public/nuxt-preview/{key}).
 * A deploy that changes one doesn't touch the catalog — check() flags it as
 * "update available", and apply() (the Admin › Templates "Update template"
 * button) publishes it as the template's next version. Sites then move onto
 * it with "Update sites", as for every other template.
 */
class BuiltinTemplateUpdates
{
    public function __construct(private TemplateCatalogWriter $writer) {}

    /** What the repo holds for a built-in: package files + its built site, as one hash. */
    public function fingerprint(string $key): string
    {
        $files = [];
        foreach ([resource_path('templates/'.$key), public_path('nuxt-preview/'.$key)] as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $f) {
                $files[$f->getRelativePathname().'@'.basename($root)] = sha1_file($f->getPathname());
            }
        }
        ksort($files);

        return sha1(json_encode($files));
    }

    /**
     * Flag every catalogued built-in whose repo copy differs from its latest
     * published version.
     *
     * @return array<string, string> key => 'update' | 'current' | 'baseline' | 'not-in-catalog'
     */
    public function check(): array
    {
        $out = [];
        foreach (TemplateRegistry::all() as $contract) {
            $key = $contract->key();
            $template = $this->templateFor($key);
            if (! $template) {
                $out[$key] = 'not-in-catalog';

                continue;
            }
            $hash = $this->fingerprint($key);
            $latest = $template->latestVersion;
            $known = (string) ($latest?->manifest['source_hash'] ?? '');

            if ($known === '' && $latest && $latest->version === $contract->version()) {
                // First run for this template: same version string as published →
                // treat the repo copy as what's live and remember its hash.
                $latest->update(['manifest' => array_merge((array) $latest->manifest, ['source_hash' => $hash])]);
                $template->update(['pending_update' => null]);
                $out[$key] = 'baseline';

                continue;
            }
            if ($known === $hash) {
                $template->update(['pending_update' => null]);
                $out[$key] = 'current';

                continue;
            }
            $template->update(['pending_update' => [
                'version' => $contract->version(),
                'hash' => $hash,
                'detected_at' => now()->toIso8601String(),
            ]]);
            $out[$key] = 'update';
        }

        return $out;
    }

    /** Publish the repo copy as the template's next version (the "Update template" button). */
    public function apply(Template $template): TemplateVersion
    {
        $key = (string) $template->builtin_key;
        $contract = TemplateRegistry::find($key);
        abort_unless($template->source === 'builtin' && $contract instanceof TemplateContract, 422, 'Not a built-in template.');

        $hash = $this->fingerprint($key);
        $label = $contract->version();
        // Same version string but different content → a distinct version, so
        // "Update sites" sees the sites as behind and can move them.
        $existing = $template->versions()->where('version', $label)->first();
        if ($existing && ($existing->manifest['source_hash'] ?? null) !== $hash) {
            $label .= '+'.substr($hash, 0, 7);
        }

        $version = $this->writer->publishVersion($template, $contract, $label, ['source_hash' => $hash]);
        $template->update(['pending_update' => null]);

        return $version;
    }

    private function templateFor(string $key): ?Template
    {
        return Template::where('source', 'builtin')
            ->where(fn ($q) => $q->where('builtin_key', $key)->orWhere('slug', $key))
            ->first();
    }
}
