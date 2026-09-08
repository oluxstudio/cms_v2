<?php

return [

    /*
    | Filesystem disk used for template assets, thumbnails and rendered previews.
    | Defaults to the local-served "templates" disk; set TEMPLATES_DISK=s3 (and the
    | AWS_* vars) to serve from object storage + CDN at marketplace scale.
    */
    'disk' => env('TEMPLATES_DISK', 'templates'),

    /*
    | Currency every catalog template is priced and sold in.
    */
    'currency' => env('TEMPLATES_CURRENCY', 'gbp'),

    /*
    | Marketplace listing page size.
    */
    'per_page' => env('TEMPLATES_PER_PAGE', 12),

    /*
    | Moderator emails — users who can approve/reject submitted templates.
    | Comma-separated in TEMPLATES_MODERATORS.
    */
    'moderators' => array_filter(array_map('trim', explode(',', (string) env('TEMPLATES_MODERATORS', 'devlobu@gmail.com')))),

    /*
    | Staging folder where template authors drop full Nuxt app submissions.
    | Mounted into the container from ../templates (see docker-compose.yml).
    | The Submissions review UI scans this path, extracts, and publishes on Accept.
    */
    'staging_path' => env('TEMPLATE_STAGING_PATH', '/var/www/templates-staging'),

    /*
    | UGC upload limits (security hardening for the .zip import path).
    */
    // Zip-of-Nuxt-APP intake (Submissions upload / template:import) — a
    // different profile from the split-file package limits below.
    'limits_app' => [
        'max_files' => 600,
        'max_total_mb' => 60,
        'max_file_mb' => 10,
        'allowed_ext' => ['vue', 'ts', 'js', 'mjs', 'json', 'css', 'scss', 'md', 'txt',
            'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot'],
        'denied_dirs' => ['node_modules', '.nuxt', '.output', '.git', '.github'],
    ],

    // Repo-first intake: PAT for private https clones + push-webhook secret.
    'git' => [
        'token' => env('TEMPLATES_GIT_TOKEN'),
        'webhook_secret' => env('TEMPLATES_REPO_WEBHOOK_SECRET'),
    ],

    'limits' => [
        'max_files' => 200,
        'max_total_mb' => 30,   // total uncompressed
        'max_file_mb' => 8,    // per extracted file
        'max_pages' => 30,
        'allowed_ext' => ['json', 'css', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'md'],
    ],

];
