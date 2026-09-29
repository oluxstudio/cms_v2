<?php

namespace App\Support;

use App\Templates\TemplateAppRegistry;

/**
 * Where a template app's files live, by key. ONE resolver for every surface.
 *
 * First-party templates ship in the image: app source in templates/{key},
 * CMS package in resources/templates/{key}, built shell in
 * public/nuxt-preview/{key}. Client-uploaded templates (keys "u-…") live on
 * the persistent storage volume instead — everything outside storage/ is
 * replaced on every deploy — with the shell under storage/app/public so it is
 * served statically through the public/storage link.
 */
class TemplatePaths
{
    public const UPLOAD_PREFIX = 'u-';

    public static function isUpload(?string $key): bool
    {
        return is_string($key) && str_starts_with($key, self::UPLOAD_PREFIX);
    }

    /** Root holding every uploaded template: {root}/{key}/app, {root}/{key}/package. */
    public static function uploadsRoot(): string
    {
        return rtrim((string) config('templates.uploads.path', storage_path('app/user-templates')), '/');
    }

    /** The Nuxt app source (after the publisher's rewrite). */
    public static function appDir(string $key): string
    {
        if ($key === TemplateAppRegistry::BLANK) {
            return base_path('nuxt-template');
        }

        return self::isUpload($key) ? self::uploadsRoot()."/{$key}/app" : base_path("templates/{$key}");
    }

    /** The CMS package: template.json, pages/, layouts/, tokens/… */
    public static function packageDir(string $key): string
    {
        return self::isUpload($key) ? self::uploadsRoot()."/{$key}/package" : resource_path("templates/{$key}");
    }

    /** Absolute directory of the built static shell. */
    public static function shellDir(string $key): string
    {
        if ($key === TemplateAppRegistry::BLANK) {
            return public_path('nuxt-preview');
        }

        return self::isUpload($key)
            ? storage_path("app/public/template-shells/{$key}")
            : public_path("nuxt-preview/{$key}");
    }

    /** The shell's public base path, with leading and trailing slash. */
    public static function shellBase(string $key): string
    {
        if ($key === TemplateAppRegistry::BLANK) {
            return '/nuxt-preview/';
        }

        return self::isUpload($key) ? "/storage/template-shells/{$key}/" : "/nuxt-preview/{$key}/";
    }

    public static function hasShell(string $key): bool
    {
        return is_file(self::shellDir($key).'/index.html');
    }

    /** Absolute URL of the shell root (no query string). */
    public static function shellUrl(string $key): string
    {
        return rtrim(url(self::shellBase($key)), '/').'/';
    }
}
