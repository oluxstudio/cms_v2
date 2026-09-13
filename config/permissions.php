<?php

/*
|--------------------------------------------------------------------------
| Access control — the permission matrix
|--------------------------------------------------------------------------
| Single source of truth for every permission a team-member role can hold
| (same pattern as config/features.php). Keys use "resource.action".
| Roles never grant access by name — they carry a list of these keys.
|
| Account owners and super admins implicitly hold every permission.
*/

return [

    // Grouped catalog — groups shape the checkbox grid in the Roles editor.
    'groups' => [
        'Content' => [
            'pages.view' => 'View pages',
            'pages.manage' => 'Create & edit pages',
            'posts.view' => 'View posts',
            'posts.manage' => 'Create & edit posts',
            'collections.view' => 'View collections',
            'collections.manage' => 'Create & edit collections',
            'components.view' => 'View components',
            'components.manage' => 'Create & edit components',
            'media.view' => 'View assets',
            'media.manage' => 'Upload & delete assets',
        ],
        'Audience' => [
            'forms.view' => 'View forms & submissions',
            'forms.manage' => 'Create & edit forms',
            'contacts.view' => 'View contacts',
            'messages.view' => 'View team messages',
            'messages.send' => 'Send team messages',
            'contacts.manage' => 'Edit contacts & lifecycle',
            'polls.view' => 'View polls & results',
            'polls.manage' => 'Create & edit polls',
        ],
        'Commerce' => [
            'store.view' => 'View products',
            'store.manage' => 'Create & edit products',
            'orders.view' => 'View orders',
            'orders.manage' => 'Process orders',
            'bookings.view' => 'View bookings',
            'bookings.manage' => 'Manage bookings & services',
            'invoices.view' => 'View invoices',
            'invoices.manage' => 'Create & send invoices',
            'estimates.view' => 'View estimates',
            'estimates.manage' => 'Configure estimator fields, calculations & emails',
            'donations.view' => 'View donations',
        ],
        'Site' => [
            'analytics.view' => 'View analytics',
            'builder.manage' => 'Use the site builder',
            'addons.manage' => 'Enable / configure add-ons',
            'publish.manage' => 'Put the site live / manage domain',
            'team.manage' => 'Manage team & roles',
        ],
    ],

    // Admin-page segment → permission required to see/open it.
    // Segments not listed here are open to every account member.
    'nav' => [
        'pages' => 'pages.view',
        'posts' => 'posts.view',
        'collections' => 'collections.view',
        'components' => 'components.view',
        'media' => 'media.view',
        'forms' => 'forms.view',
        'submissions' => 'forms.view',
        'contacts' => 'contacts.view',
        'messages' => 'messages.view',
        'store' => 'store.view',
        'orders' => 'orders.view',
        'bookings' => 'bookings.view',
        'invoices' => 'invoices.view',
        'estimates' => 'estimates.view',
        'polls' => 'polls.view',
        'donations' => 'donations.view',
        'analytics' => 'analytics.view',
        'alerts' => 'analytics.view',
        'blocks' => 'builder.manage',
        'marketplace' => 'addons.manage',
        'payments' => 'addons.manage',
        'designs' => 'builder.manage',
        'publish' => 'publish.manage',
        'team' => 'team.manage',
    ],

    // Default roles seeded into every account the first time its team page
    // is opened. '*' = every permission. Editable per account afterwards.
    'roles' => [
        'admin' => [
            'name' => 'Admin',
            'description' => 'Full access to everything in this account, including team management.',
            'permissions' => ['*'],
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Creates and edits content and audience data; sees commerce read-only.',
            'permissions' => [
                'pages.view', 'pages.manage', 'posts.view', 'posts.manage',
                'collections.view', 'collections.manage', 'components.view', 'components.manage',
                'media.view', 'media.manage',
                'forms.view', 'forms.manage', 'contacts.view', 'messages.view', 'messages.send', 'contacts.manage',
                'store.view', 'orders.view', 'bookings.view', 'invoices.view',
                'estimates.view', 'donations.view', 'analytics.view', 'builder.manage',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only access — can look at everything, change nothing.',
            'permissions' => [
                'pages.view', 'posts.view', 'collections.view', 'components.view', 'media.view',
                'forms.view', 'contacts.view', 'messages.view', 'messages.send', 'store.view', 'orders.view',
                'bookings.view', 'invoices.view', 'estimates.view', 'donations.view',
                'analytics.view',
            ],
        ],

        'manager' => [
            'name' => 'Manager',
            'description' => 'Runs the business day-to-day — everything except managing the team and roles.',
            'permissions' => [
                'pages.view', 'pages.manage', 'posts.view', 'posts.manage',
                'collections.view', 'collections.manage', 'components.view', 'components.manage',
                'media.view', 'media.manage', 'forms.view', 'forms.manage',
                'contacts.view', 'messages.view', 'messages.send', 'contacts.manage', 'store.view', 'store.manage',
                'orders.view', 'orders.manage', 'bookings.view', 'bookings.manage',
                'invoices.view', 'invoices.manage', 'estimates.view', 'estimates.manage',
                'donations.view', 'analytics.view', 'builder.manage', 'addons.manage', 'publish.manage',
            ],
        ],
        'staff' => [
            'name' => 'Staff',
            'description' => 'Front-of-house: sees and manages bookings and looks up customers. No site editing. (Sees all bookings, not only their own.)',
            'permissions' => ['bookings.view', 'bookings.manage', 'contacts.view', 'messages.view', 'messages.send'],
        ],
        'accountant' => [
            'name' => 'Accountant',
            'description' => 'Money only: invoices, orders, estimates, donations and the numbers.',
            'permissions' => ['invoices.view', 'invoices.manage', 'orders.view', 'estimates.view', 'donations.view', 'analytics.view'],
        ],
        'content-editor' => [
            'name' => 'Content editor',
            'description' => 'Writes and designs: pages, posts, collections, components and assets.',
            'permissions' => [
                'pages.view', 'pages.manage', 'posts.view', 'posts.manage',
                'collections.view', 'collections.manage', 'components.view', 'components.manage',
                'media.view', 'media.manage', 'forms.view', 'builder.manage',
            ],
        ],
    ],

    // Invitations die after this many days if not accepted.
    'invite_expiry_days' => 7,
];
