<?php

/*
|--------------------------------------------------------------------------
| Site admin page registry — title + one-line description per page
|--------------------------------------------------------------------------
| Keyed by the URL segment after the site slug (/{site}/{segment}). Used by
| the shared "selected" layout to render a consistent breadcrumb
| (Site › Page) on every page, and by pages that don't own a bespoke header
| to render their title + intro. Unknown segments fall back to a prettified
| segment name with no description.
*/

return [
    'dashboard'    => ['title' => 'Dashboard',    'description' => 'An overview of your site — recent activity, leads and quick actions.'],
    'pages'        => ['title' => 'Pages',        'description' => 'The pages of your site and the components attached to each.'],
    'posts'        => ['title' => 'Posts',        'description' => 'Write, publish and see which posts your visitors love.'],
    'collections'  => ['title' => 'Collections',  'description' => 'Structured content lists — a field schema and its entries, served to your site.'],
    'components'   => ['title' => 'Components',    'description' => 'Standalone content components — build the nodes once, attach to pages or link collections anywhere.'],
    'media'        => ['title' => 'Assets',        'description' => 'Your image, video and document library — reused across the site.'],
    'forms'        => ['title' => 'Forms',         'description' => 'Build forms and review the submissions they capture.'],
    'contacts'     => ['title' => 'Contacts',      'description' => 'Everyone who reached out — leads and their lifecycle from first touch to won.'],
    'analytics'    => ['title' => 'Analytics',     'description' => 'Traffic, engagement and conversion at a glance.'],
    'publish'      => ['title' => 'Go live',       'description' => 'Put the site live on its own domain with free SSL.'],
    'api-docs'     => ['title' => 'API docs',      'description' => 'Every endpoint your site can call, with parameters and examples.'],
    'api-keys'     => ['title' => 'API keys',      'description' => 'Bearer tokens scoped to this site for the API and MCP agents.'],
    'emails'       => ['title' => 'Emails',        'description' => 'The branded receipt every visitor gets on a submission — edit its copy and logo.'],
    'marketplace'  => ['title' => 'Add-ons',       'description' => 'Enable and configure installable features for this site.'],
    'team'         => ['title' => 'Team',          'description' => 'Invite people, assign roles and control what each can do.'],
    'todos'        => ['title' => 'Tasks',         'description' => 'Track the work to be done on this site.'],
    'alerts'       => ['title' => 'Alerts',        'description' => 'Notifications and important events for this site.'],
    'messages'     => ['title' => 'Messages',      'description' => 'Conversations with your contacts.'],
    'store'        => ['title' => 'Store',         'description' => 'Your products and everything shoppers can buy.'],
    'orders'       => ['title' => 'Orders',        'description' => 'Incoming orders and their fulfilment status.'],
    'bookings'     => ['title' => 'Bookings',      'description' => 'Appointments, stays and trips your customers have booked.'],
    'estimates'    => ['title' => 'Estimates',     'description' => 'Named estimators, their formulas and the leads they capture.'],
    'invoices'     => ['title' => 'Invoices',      'description' => 'Draft, send and track payment on your invoices.'],
    'donations'    => ['title' => 'Donations',     'description' => 'Contributions received through your donation pages.'],
    'templates'    => ['title' => 'Templates',     'description' => 'Design templates you can apply to this site.'],
    'submissions'  => ['title' => 'Submissions',   'description' => 'Raw submissions captured from your site.'],
];
