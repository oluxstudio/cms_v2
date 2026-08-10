<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The visitor submission-receipt email template: a canonical, ordered set of
 * sections the site admin can reorder / toggle / edit (stored as the site
 * attribute email.receipt_sections), plus the shared placeholder engine used
 * by BOTH the SubmissionReceipt mailable and the SiteEmailsPage live preview.
 *
 * Placeholders: {name} {site} {type}, {field:<key>} for one submitted value,
 * and {fields} for the whole submission as "Label: value" lines.
 */
class EmailTemplate
{
    /** Section keys whose body text the admin edits (logo + summary render dynamically). */
    public const EDITABLE = ['greeting', 'intro', 'footer'];

    /**
     * The default template — order + enabled + default copy. Editing/reordering
     * on the Emails page overrides this per site; anything absent falls back here.
     *
     * @return list<array{key:string,enabled:bool,text:?string}>
     */
    public static function defaultSections(): array
    {
        return [
            ['key' => 'logo', 'enabled' => true, 'text' => null],
            ['key' => 'greeting', 'enabled' => true, 'text' => 'Hi {name},'],
            ['key' => 'intro', 'enabled' => true, 'text' => "Thanks for reaching out to {site}. We've received your {type} and someone will get back to you shortly."],
            ['key' => 'summary', 'enabled' => true, 'text' => null],
            ['key' => 'footer', 'enabled' => true, 'text' => 'This message was sent by {site}. No action is needed — it\'s a copy for your records.'],
        ];
    }

    /** Human label for a section key (used by the editor + preview). */
    public static function label(string $key): string
    {
        return [
            'logo' => 'Logo / header',
            'greeting' => 'Greeting',
            'intro' => 'Message',
            'summary' => 'Submission summary',
            'footer' => 'Footer',
        ][$key] ?? Str::headline($key);
    }

    /**
     * Merge a stored section list over the defaults: keeps the stored order,
     * drops unknown keys, and appends any default section the stored list is
     * missing (so a new section added to the app still shows up, disabled last).
     *
     * @param  mixed  $stored  the raw email.receipt_sections attribute (array|null|json)
     * @return list<array{key:string,enabled:bool,text:?string}>
     */
    public static function resolveSections($stored): array
    {
        $defaults = collect(self::defaultSections())->keyBy('key');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        if (! is_array($stored) || $stored === []) {
            return self::defaultSections();
        }

        $out = [];
        $seen = [];
        foreach ($stored as $row) {
            $key = $row['key'] ?? null;
            if (! $key || ! $defaults->has($key) || in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;
            $default = $defaults->get($key);
            $out[] = [
                'key' => $key,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'text' => in_array($key, self::EDITABLE, true)
                    ? (($row['text'] ?? null) !== null ? (string) $row['text'] : $default['text'])
                    : null,
            ];
        }
        // Append any defaults the stored config didn't mention.
        foreach ($defaults as $key => $default) {
            if (! in_array($key, $seen, true)) {
                $out[] = $default;
            }
        }

        return $out;
    }

    /**
     * Resolve placeholders in a piece of text.
     *
     * @param  array{name?:?string,site?:string,type?:string}  $ctx
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

        return strtr($text, [
            '{name}' => ($ctx['name'] ?? null) ?: 'there',
            '{site}' => $ctx['site'] ?? '',
            '{type}' => $ctx['type'] ?? '',
        ]);
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
