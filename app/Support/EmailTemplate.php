<?php

namespace App\Support;

use App\Models\Form;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * The shared engine behind every admin-editable outbound email: a canonical,
 * ordered set of sections per template (catalog in config/email_templates.php)
 * that the site admin can reorder / toggle / edit, plus the placeholder engine
 * used by BOTH the mailables and the SiteEmailsPage live preview.
 *
 * Storage: site attributes email.tpl.{key}.subject / .sections — except the
 * original `receipt` template, which keeps its legacy email.receipt_* keys.
 *
 * Placeholders: any {token} present in the ctx map, {field:<key>} for one
 * submitted value, and {fields} for the whole submission as "Label: value".
 */
class EmailTemplate
{
    /** Legacy alias — the receipt template's editable keys. */
    public const EDITABLE = ['greeting', 'intro', 'footer'];

    public static function defaultSubject(string $key = 'receipt'): string
    {
        return (string) EmailTemplateCatalog::get($key)['subject'];
    }

    /** The two site-attribute names holding a template's customisation. */
    public static function attrKeys(string $key): array
    {
        if ($key === 'receipt') {
            return ['email.receipt_subject', 'email.receipt_sections'];
        }

        return ["email.tpl.{$key}.subject", "email.tpl.{$key}.sections"];
    }

    /**
     * The template a mailable should use: the site's stored customisation over
     * the catalog defaults (honouring legacy single-body attrs where mapped).
     *
     * @return array{key:string,subject:string,sections:list<array{key:string,enabled:bool,text:?string}>}
     */
    public static function forKey(Site $site, string $key): array
    {
        $entry = EmailTemplateCatalog::get($key);
        [$subjectAttr, $sectionsAttr] = self::attrKeys($key);

        $stored = $site->getAttr($sectionsAttr);
        $subject = $site->getAttr($subjectAttr);

        // Legacy shims: a pre-sections customisation maps into one section.
        if (empty($stored)) {
            if ($key === 'receipt' && ($legacyBody = $site->getAttr('email.receipt_body'))) {
                $stored = collect(self::defaultSections($key))->map(function ($s) use ($legacyBody) {
                    if ($s['key'] === 'intro') {
                        $s['text'] = $legacyBody;
                    }
                    if ($s['key'] === 'greeting') {
                        $s['enabled'] = false; // the legacy body included its own greeting
                    }

                    return $s;
                })->all();
            } elseif (! empty($entry['legacy']['intro']) && ($legacyBody = $site->getAttr($entry['legacy']['intro']))) {
                $stored = collect(self::defaultSections($key))->map(function ($s) use ($legacyBody) {
                    if ($s['key'] === 'intro') {
                        $s['text'] = $legacyBody;
                    }

                    return $s;
                })->all();
            }
        }
        if ($subject === null && ! empty($entry['legacy']['subject'])) {
            $subject = $site->getAttr($entry['legacy']['subject']);
        }

        return [
            'key' => $key,
            'subject' => (string) ($subject ?? $entry['subject']),
            'sections' => self::resolveSections($stored, $key),
        ];
    }

    /** Persist a site's customisation of one template. */
    public static function saveFor(Site $site, string $key, string $subject, array $sections): void
    {
        [$subjectAttr, $sectionsAttr] = self::attrKeys($key);
        $editable = self::editable($key);
        $clean = collect($sections)->map(fn ($s) => [
            'key' => $s['key'],
            'enabled' => (bool) ($s['enabled'] ?? true),
            'text' => in_array($s['key'], $editable, true) ? ($s['text'] ?? null) : null,
        ])->values()->all();

        $site->setAttr($subjectAttr, trim($subject));
        $site->setAttr($sectionsAttr, json_encode($clean));
    }

    /** Drop a site's customisation — the template falls back to catalog defaults. */
    public static function resetFor(Site $site, string $key): void
    {
        [$subjectAttr, $sectionsAttr] = self::attrKeys($key);
        $site->forgetAttr($subjectAttr);
        $site->forgetAttr($sectionsAttr);
    }

    /**
     * The SITE-WIDE default receipt template (legacy entry point — kept for
     * SubmissionReceipt / forForm / the Forms page).
     */
    public static function siteDefault(Site $site): array
    {
        $tpl = self::forKey($site, 'receipt');

        return ['subject' => $tpl['subject'], 'sections' => $tpl['sections']];
    }

    /**
     * The template to use for a submission: the form's own customised template
     * when set, otherwise the site default.
     */
    public static function forForm(?Form $form, Site $site): array
    {
        $tpl = $form?->email_template;
        if (is_array($tpl) && ! empty($tpl['customized'])) {
            return [
                'subject' => (string) ($tpl['subject'] ?? self::defaultSubject()),
                'sections' => self::resolveSections($tpl['sections'] ?? null),
            ];
        }

        return self::siteDefault($site);
    }

    /**
     * A template's default sections — order + enabled + default copy.
     *
     * @return list<array{key:string,enabled:bool,text:?string}>
     */
    public static function defaultSections(string $key = 'receipt'): array
    {
        return EmailTemplateCatalog::get($key)['sections'];
    }

    /** Section keys whose body text the admin edits for a template. */
    public static function editable(string $key = 'receipt'): array
    {
        return EmailTemplateCatalog::get($key)['editable'];
    }

    /** Human label for a section key (dynamic labels come from the catalog). */
    public static function label(string $key, string $tplKey = 'receipt'): string
    {
        $shared = [
            'logo' => 'Logo / header',
            'greeting' => 'Greeting',
            'intro' => 'Message',
            'outro' => 'Sign-off message',
            'footer' => 'Footer',
        ];
        $dynamic = EmailTemplateCatalog::exists($tplKey)
            ? (EmailTemplateCatalog::get($tplKey)['dynamic'] ?? [])
            : [];

        return $dynamic[$key] ?? $shared[$key] ?? Str::headline($key);
    }

    /**
     * Merge a stored section list over a template's defaults: keeps the stored
     * order, drops unknown keys, appends missing defaults.
     *
     * @param  mixed  $stored  raw sections attribute (array|null|json)
     * @return list<array{key:string,enabled:bool,text:?string}>
     */
    public static function resolveSections($stored, string $key = 'receipt'): array
    {
        $defaults = collect(self::defaultSections($key))->keyBy('key');
        $editable = self::editable($key);

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        if (! is_array($stored) || $stored === []) {
            return self::defaultSections($key);
        }

        $out = [];
        $seen = [];
        foreach ($stored as $row) {
            $rowKey = $row['key'] ?? null;
            if (! $rowKey || ! $defaults->has($rowKey) || in_array($rowKey, $seen, true)) {
                continue;
            }
            $seen[] = $rowKey;
            $default = $defaults->get($rowKey);
            $out[] = [
                'key' => $rowKey,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'text' => in_array($rowKey, $editable, true)
                    ? (($row['text'] ?? null) !== null ? (string) $row['text'] : $default['text'])
                    : null,
            ];
        }
        foreach ($defaults as $rowKey => $default) {
            if (! in_array($rowKey, $seen, true)) {
                $out[] = $default;
            }
        }

        return $out;
    }

    /**
     * The ONE place producing the enabled + placeholder-filled section list a
     * mailable (or the live preview) renders. Text sections come back filled;
     * dynamic sections keep text=null for the view to render from its payload.
     *
     * @return list<array{key:string,enabled:bool,text:?string}>
     */
    public static function renderSections(array $tpl, array $ctx = [], array $summary = []): array
    {
        return collect($tpl['sections'])
            ->filter(fn ($s) => $s['enabled'] ?? true)
            ->map(fn ($s) => [
                'key' => $s['key'],
                'enabled' => true,
                'text' => ($s['text'] ?? null) !== null ? self::fill($s['text'], $ctx, $summary) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve placeholders in a piece of text. Every ctx key substitutes its
     * {token}; {name} falls back to "there" when empty.
     *
     * @param  array<string,mixed>  $ctx
     * @param  array<string,mixed>  $summary  submitted key => value pairs
     */
    public static function fill(string $text, array $ctx = [], array $summary = []): string
    {
        $text = preg_replace_callback(
            '/\{field:\s*([^}]+?)\s*\}/',
            fn ($m) => self::fieldValue($summary, $m[1]),
            $text
        );
        $text = str_replace('{fields}', self::fieldsBlock($summary), $text);

        // {site}/{type} always resolve (legacy behaviour: blank when absent).
        $map = [
            '{name}' => ($ctx['name'] ?? null) ?: 'there',
            '{site}' => (string) ($ctx['site'] ?? ''),
            '{type}' => (string) ($ctx['type'] ?? ''),
        ];
        foreach ($ctx as $token => $value) {
            if ($token === 'name') {
                continue;
            }
            $map['{'.$token.'}'] = (string) ($value ?? '');
        }

        return strtr($text, $map);
    }

    /** A submitted value by field key (case-insensitive); arrays joined. */
    public static function fieldValue(array $summary, string $key): string
    {
        foreach ($summary as $k => $v) {
            if (strcasecmp((string) $k, $key) === 0) {
                return is_array($v) ? implode(', ', $v) : (string) $v;
            }
        }

        return '';
    }

    /** All submitted fields as "Label: value" lines (for {fields}). */
    public static function fieldsBlock(array $summary): string
    {
        return collect($summary)
            ->map(fn ($v, $k) => Str::headline((string) $k).': '.(is_array($v) ? implode(', ', $v) : $v))
            ->implode("\n");
    }
}
