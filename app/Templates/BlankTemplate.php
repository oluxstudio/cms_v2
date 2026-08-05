<?php

namespace App\Templates;

class BlankTemplate implements TemplateContract
{
    public function key(): string          { return 'blank'; }
    public function name(): string         { return 'Blank Site'; }
    public function description(): string  { return 'A clean 3-page starting point (Home, About, Contact) with placeholder layouts and lorem content — ready to make your own.'; }
    public function category(): string     { return 'Starter'; }
    public function gradientClass(): string { return 'from-gray-400 to-gray-600'; }
    public function accentColor(): string  { return '#6366f1'; }
    public function author(): string       { return 'Olux Studio'; }
    public function version(): string      { return '1.1.0'; }
    public function createdAt(): string    { return '2025-01-01'; }
    public function tags(): array          { return ['Blank', 'Starter', 'Custom']; }
    public function features(): array      {
        return [
            'Home, About and Contact pages pre-wired',
            'Section layouts for hero, text, profile, FAQ and contact form',
            'Lorem placeholder content and assets — swap in your own',
            'Sections reorder freely; bind your components in the Content tab',
        ];
    }

    /** Neutral default theme — a clean starting point. */
    public function theme(): array
    {
        return [
            'font'      => 'Inter',
            'accent'    => '#6366f1',
            'navy'      => '#111827',
            'surface'   => '#f8fafc',
            'text'      => '#1a1f36',
            'muted'     => '#6b7280',
            'radius'    => '12px',
            'base_size' => '16px',
        ];
    }

    public function pages(): array
    {
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

        return [
            // ── Home: header · hero · header-paragraph · contact form · footer ──
            [
                'name'     => 'Home',
                'url'      => '/',
                'keywords' => 'home',
                'blocks'   => [
                    ['type' => 'navbar', 'variant' => 'simple'],
                    [
                        'type' => 'hero', 'variant' => 'centered', 'name' => 'Hero',
                        'nodes' => [
                            ['label' => 'Headline',  'type' => 'text', 'value' => 'Welcome to {site_name}',          'order' => 0],
                            ['label' => 'Subtitle',  'type' => 'text', 'value' => 'A clean starting point for your new site. ' . $lorem, 'order' => 1],
                            ['label' => 'CTA Label', 'type' => 'text', 'value' => 'Get Started',                     'order' => 2],
                            ['label' => 'CTA URL',   'type' => 'url',  'value' => '/contact',                        'order' => 3],
                        ],
                    ],
                    [
                        'type' => 'text', 'variant' => 'centered', 'name' => 'Introduction',
                        'nodes' => [
                            ['label' => 'Headline', 'type' => 'text', 'value' => 'A short headline about your business', 'order' => 0],
                            ['label' => 'Body',     'type' => 'text', 'value' => $lorem . ' ' . $lorem,                  'order' => 1],
                        ],
                    ],
                    [
                        'type' => 'contact', 'variant' => 'stacked', 'name' => 'Contact Form',
                        'nodes' => [
                            ['label' => 'Headline', 'type' => 'text', 'value' => 'Get in Touch',               'order' => 0],
                            ['label' => 'Body',     'type' => 'text', 'value' => 'Send us a message and we will get back to you.', 'order' => 1],
                            ['label' => 'Email',    'type' => 'url',  'value' => 'mailto:hello@example.com',    'order' => 2],
                        ],
                    ],
                    ['type' => 'footer', 'variant' => 'columns'],
                ],
            ],

            // ── About Us: header · profile-description panel · faq · footer ──
            [
                'name'     => 'About Us',
                'url'      => '/about',
                'keywords' => 'about',
                'blocks'   => [
                    ['type' => 'navbar', 'variant' => 'simple'],
                    [
                        'type' => 'profile', 'variant' => 'left', 'name' => 'Profile',
                        'nodes' => [
                            ['label' => 'Name', 'type' => 'text',  'value' => 'About {site_name}',   'order' => 0],
                            ['label' => 'Role', 'type' => 'text',  'value' => 'Who we are',           'order' => 1],
                            ['label' => 'Bio',  'type' => 'text',  'value' => $lorem . ' ' . $lorem,  'order' => 2],
                            ['label' => 'Avatar', 'type' => 'image', 'value' => '',                   'order' => 3],
                        ],
                    ],
                    [
                        'type' => 'faq', 'variant' => 'list', 'name' => 'FAQ',
                        'nodes' => [
                            ['label' => 'Section Title', 'type' => 'text', 'value' => 'Frequently Asked Questions', 'order' => 0],
                            ['label' => 'Question 1',    'type' => 'text', 'value' => 'What do you offer?',         'order' => 1],
                            ['label' => 'Answer 1',      'type' => 'text', 'value' => $lorem,                        'order' => 2],
                            ['label' => 'Question 2',    'type' => 'text', 'value' => 'How do I get started?',      'order' => 3],
                            ['label' => 'Answer 2',      'type' => 'text', 'value' => $lorem,                        'order' => 4],
                        ],
                    ],
                    ['type' => 'footer', 'variant' => 'columns'],
                ],
            ],

            // ── Contact Us: header · contact form · footer ──
            [
                'name'     => 'Contact Us',
                'url'      => '/contact',
                'keywords' => 'contact',
                'blocks'   => [
                    ['type' => 'navbar', 'variant' => 'simple'],
                    [
                        'type' => 'contact', 'variant' => 'split', 'name' => 'Contact Info',
                        'nodes' => [
                            ['label' => 'Headline', 'type' => 'text', 'value' => 'Contact Us',                   'order' => 0],
                            ['label' => 'Body',     'type' => 'text', 'value' => 'We would love to hear from you.', 'order' => 1],
                            ['label' => 'Email',    'type' => 'url',  'value' => 'mailto:hello@example.com',      'order' => 2],
                            ['label' => 'Phone',    'type' => 'text', 'value' => '+1 (555) 000-0000',             'order' => 3],
                            ['label' => 'Address',  'type' => 'text', 'value' => '123 Main St, City, Country',    'order' => 4],
                        ],
                    ],
                    ['type' => 'footer', 'variant' => 'columns'],
                ],
            ],
        ];
    }
}
