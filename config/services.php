<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token'  => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'               => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    'twitter' => [
        'client_id'     => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect'      => env('TWITTER_REDIRECT_URI', '/auth/twitter/callback'),
    ],

    'instagram' => [
        'client_id'     => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect'      => env('INSTAGRAM_REDIRECT_URI', '/auth/instagram/callback'),
    ],

    'tiktok' => [
        'client_id'     => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect'      => env('TIKTOK_REDIRECT_URI', '/auth/tiktok/callback'),
    ],

    // Stripe API version only — per-site secret/publishable/webhook keys are
    // stored encrypted per site in `site_payment_settings`, not in env.
    'stripe' => [
        // null = the Stripe account's default API version (avoids "outdated
        // version" rejections on accounts created after a version was pinned).
        'api_version' => env('STRIPE_API_VERSION'),
    ],

    // Platform-level Stripe + Connect for the template marketplace (distinct from
    // each site's own Stripe in SitePaymentSettings). Sales use destination charges;
    // the platform keeps `fee_percent` and pays the rest out to the creator.
    'stripe_platform' => [
        'secret'         => env('STRIPE_PLATFORM_SECRET'),
        'webhook_secret' => env('STRIPE_PLATFORM_WEBHOOK_SECRET'),
        'fee_percent'    => (float) env('TEMPLATES_FEE_PERCENT', 20),
    ],

    // LLM driver — 'deepseek' | 'anthropic' | 'ollama'
    'llm' => [
        'driver' => env('LLM_DRIVER', 'anthropic'),
    ],

    // DeepSeek cloud API — fast, cheap (~$0.14/1M tokens), strong tool-calling.
    // Sign up: https://platform.deepseek.com
    // Models: deepseek-chat (V3, recommended) | deepseek-reasoner (R1, slower)
    'deepseek' => [
        'key'   => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    // Claude API — Anthropic's cloud model. Best quality, higher cost.
    // When the key is absent the assistant falls back to a deterministic command parser.
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

    // Ollama — local LLM server (OpenAI-compatible). Free but requires a GPU for speed.
    // Build custom model: ollama create cms-operator -f resources/ai/ollama/Modelfile.cms
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
        'model'    => env('OLLAMA_MODEL', 'cms-operator'),
    ],

];
