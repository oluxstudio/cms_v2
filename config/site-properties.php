<?php

/*
|--------------------------------------------------------------------------
| Site properties — the schema of the "Site Properties" component
|--------------------------------------------------------------------------
| Single source of truth for a site's business profile. Every field is a
| NODE on the site's page-less "Site Properties" component (matched by its
| `label`), so the Properties page and the Edit page edit the same data.
| Add a field here and every site's component gains the node on next use.
|
| field:    key => [label, input, tab, help?, placeholder?, options?, rules?, default?]
|   input:  text | textarea | email | url | tel | number | select | toggle |
|           image | date | hours     (hours = one day's "Closed" / "09:00-17:00, …")
| repeater: key => [prefix, tab, title, add, fields => [sub => [label, input, …]]]
|           stored as nodes "{prefix} {n} {sub label}" (the useOluxContent
|           items() convention the Edit page and templates already use).
*/

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

return [

    'component' => 'Site Properties',
    'tag' => 'site:properties',

    'tabs' => [
        'brand' => 'Brand',
        'colours' => 'Colours',
        'business' => 'Business',
        'hours' => 'Hours',
        'contact' => 'Contact & social',
        'legal' => 'Legal',
        'seo' => 'SEO & tracking',
        'locale' => 'Locale & status',
        'assistant' => 'Assistant',
        'variables' => 'Variables',
    ],

    // schema.org LocalBusiness subtypes offered for "Business type".
    'business_types' => [
        'LocalBusiness' => 'Local business (general)',
        'HairSalon' => 'Hair salon / barber',
        'BeautySalon' => 'Beauty salon',
        'DaySpa' => 'Spa',
        'NailSalon' => 'Nail salon',
        'Plumber' => 'Plumber',
        'Electrician' => 'Electrician',
        'HVACBusiness' => 'Heating & air conditioning',
        'RoofingContractor' => 'Roofer',
        'GeneralContractor' => 'Builder / contractor',
        'HousePainter' => 'Painter & decorator',
        'Locksmith' => 'Locksmith',
        'MovingCompany' => 'Removals',
        'AutoRepair' => 'Garage / car repair',
        'MedicalClinic' => 'Medical clinic',
        'Dentist' => 'Dentist',
        'Physician' => 'Doctor / GP',
        'Optician' => 'Optician',
        'VeterinaryCare' => 'Vet',
        'HealthAndBeautyBusiness' => 'Health & beauty',
        'ExerciseGym' => 'Gym / fitness',
        'Restaurant' => 'Restaurant',
        'CafeOrCoffeeShop' => 'Café',
        'Bakery' => 'Bakery',
        'Store' => 'Shop',
        'Florist' => 'Florist',
        'ProfessionalService' => 'Professional services',
        'LegalService' => 'Solicitor / legal',
        'AccountingService' => 'Accountant',
        'RealEstateAgent' => 'Estate agent',
        'ChildCare' => 'Childcare / nursery',
        'EducationalOrganization' => 'School / tuition',
        'Church' => 'Church',
        'PlaceOfWorship' => 'Place of worship',
        'NGO' => 'Charity / non-profit',
        'Organization' => 'Organisation',
    ],

    'fields' => [
        // ── Brand ──
        'site_name' => ['label' => 'Site Name', 'input' => 'text', 'tab' => 'brand', 'rules' => ['required', 'max:120'], 'placeholder' => 'Grace Way Church', 'help' => 'Must be different from every other site’s name. Your web address stays the same — change it on the Publish page.'],
        'short_name' => ['label' => 'Short Name', 'input' => 'text', 'tab' => 'brand', 'rules' => ['max:30'], 'placeholder' => 'Grace Way', 'help' => 'For browser tabs, app icons and email subjects.'],
        'tagline' => ['label' => 'Tagline', 'input' => 'text', 'tab' => 'brand', 'rules' => ['max:160'], 'placeholder' => 'A church family in the heart of Blackburn'],
        'logo' => ['label' => 'Logo', 'input' => 'image', 'tab' => 'brand', 'help' => 'Main logo, for light backgrounds.'],
        'logo_text' => ['label' => 'Logo Text', 'input' => 'textarea', 'tab' => 'brand', 'rules' => ['max:200'], 'placeholder' => "CAC\nMount Zion\nInternational", 'help' => 'The words shown beside your logo — one line per line.'],
        'logo_subtext' => ['label' => 'Logo Subtext', 'input' => 'text', 'tab' => 'brand', 'rules' => ['max:80'], 'placeholder' => 'Blackburn.', 'help' => 'A small line under the logo text.'],
        'logo_light' => ['label' => 'Logo Light', 'input' => 'image', 'tab' => 'brand', 'help' => 'White / inverse version for dark backgrounds.'],
        'square_icon' => ['label' => 'Square Icon', 'input' => 'image', 'tab' => 'brand', 'help' => 'Square image, at least 512×512. Favicon, phone and app icons are made from it.'],
        'share_image' => ['label' => 'Share Image', 'input' => 'image', 'tab' => 'brand', 'help' => 'Shown when your site is shared on social media (1200×630 works best).'],

        // ── Business ──
        'legal_name' => ['label' => 'Legal Name', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:160'], 'placeholder' => 'Grace Way Ministries Ltd'],
        'trading_name' => ['label' => 'Trading Name', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:160']],
        'business_type' => ['label' => 'Business Type', 'input' => 'select', 'tab' => 'business', 'options' => 'business_types', 'default' => 'LocalBusiness', 'help' => 'Helps search engines understand what you do.'],
        'description' => ['label' => 'Description', 'input' => 'textarea', 'tab' => 'business', 'rules' => ['max:1000']],
        'address_street' => ['label' => 'Address Street', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:190']],
        'address_town' => ['label' => 'Address Town', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:120']],
        'address_county' => ['label' => 'Address County', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:120']],
        'address_postcode' => ['label' => 'Address Postcode', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:20']],
        'address_country' => ['label' => 'Address Country', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:80'], 'default' => 'United Kingdom'],
        'latitude' => ['label' => 'Latitude', 'input' => 'number', 'tab' => 'business', 'rules' => ['nullable', 'numeric', 'between:-90,90']],
        'longitude' => ['label' => 'Longitude', 'input' => 'number', 'tab' => 'business', 'rules' => ['nullable', 'numeric', 'between:-180,180']],
        'service_area' => ['label' => 'Service Area', 'input' => 'text', 'tab' => 'business', 'rules' => ['max:500'], 'placeholder' => 'Blackburn, Darwen, BB1, BB2', 'help' => 'Towns or postcodes you cover, separated by commas.'],
        'service_radius_km' => ['label' => 'Service Radius Km', 'display' => 'Service radius (km)', 'input' => 'number', 'tab' => 'business', 'rules' => ['nullable', 'numeric', 'min:0', 'max:1000']],
        'price_range' => ['label' => 'Price Range', 'input' => 'select', 'tab' => 'business', 'options' => ['' => '—', '£' => '£', '££' => '££', '£££' => '£££', '££££' => '££££']],
        'google_business_url' => ['label' => 'Google Business Url', 'display' => 'Google Business Profile link', 'input' => 'url', 'tab' => 'business', 'help' => 'Your Google Business Profile link.'],
        'google_review_url' => ['label' => 'Google Review Url', 'display' => 'Google review link', 'input' => 'url', 'tab' => 'business', 'help' => 'Used in review-request emails after bookings.'],
        'year_established' => ['label' => 'Year Established', 'input' => 'number', 'tab' => 'business', 'rules' => ['nullable', 'integer', 'min:1000', 'max:2100']],

        // ── Hours ──
        ...collect($days)->mapWithKeys(fn ($d) => ["hours_{$d}" => [
            'label' => 'Hours '.ucfirst($d), 'input' => 'hours', 'tab' => 'hours', 'day' => ucfirst($d),
        ]])->all(),

        // ── Contact & social ──
        'email' => ['label' => 'Email', 'input' => 'email', 'tab' => 'contact', 'rules' => ['nullable', 'email', 'max:190'], 'placeholder' => 'hello@yourbusiness.com', 'help' => 'Main address for enquiries.'],
        'whatsapp' => ['label' => 'Whatsapp', 'display' => 'WhatsApp number', 'input' => 'tel', 'tab' => 'contact', 'rules' => ['nullable', 'max:40', 'regex:/^[0-9+().\-\s]{3,40}$/']],
        'facebook' => ['label' => 'Facebook', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'instagram' => ['label' => 'Instagram', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'tiktok' => ['label' => 'Tiktok', 'display' => 'TikTok', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'linkedin' => ['label' => 'Linkedin', 'display' => 'LinkedIn', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'x' => ['label' => 'X', 'display' => 'X (Twitter)', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'youtube' => ['label' => 'Youtube', 'display' => 'YouTube', 'input' => 'url', 'tab' => 'contact', 'group' => 'Social profiles'],
        'review_google' => ['label' => 'Review Google', 'display' => 'Google reviews', 'input' => 'url', 'tab' => 'contact', 'group' => 'Review profiles'],
        'review_trustpilot' => ['label' => 'Review Trustpilot', 'input' => 'url', 'tab' => 'contact', 'group' => 'Review profiles'],
        'review_checkatrade' => ['label' => 'Review Checkatrade', 'input' => 'url', 'tab' => 'contact', 'group' => 'Review profiles'],
        'review_treatwell' => ['label' => 'Review Treatwell', 'input' => 'url', 'tab' => 'contact', 'group' => 'Review profiles'],

        // ── Legal ──
        'company_number' => ['label' => 'Company Number', 'display' => 'Companies House number', 'input' => 'text', 'tab' => 'legal', 'rules' => ['nullable', 'regex:/^([0-9]{8}|[A-Z]{2}[0-9]{6})$/i'], 'placeholder' => '01234567', 'help' => 'Companies House number (8 characters).'],
        'vat_number' => ['label' => 'Vat Number', 'display' => 'VAT number', 'input' => 'text', 'tab' => 'legal', 'rules' => ['nullable', 'regex:/^(GB)?\s?([0-9]{3}\s?[0-9]{4}\s?[0-9]{2}(\s?[0-9]{3})?|(GD|HA)[0-9]{3})$/i'], 'placeholder' => 'GB 123 4567 89'],
        'registered_office' => ['label' => 'Registered Office', 'input' => 'textarea', 'tab' => 'legal', 'rules' => ['max:500'], 'help' => 'Only if different from your trading address.'],
        'ico_number' => ['label' => 'Ico Number', 'display' => 'ICO registration number', 'input' => 'text', 'tab' => 'legal', 'rules' => ['nullable', 'regex:/^[A-Z]{1,2}[0-9]{6,8}$/i'], 'placeholder' => 'ZA123456', 'help' => 'ICO data protection registration.'],
        'privacy_policy' => ['label' => 'Privacy Policy', 'input' => 'url', 'tab' => 'legal', 'group' => 'Policy pages'],
        'terms' => ['label' => 'Terms', 'input' => 'url', 'tab' => 'legal', 'group' => 'Policy pages'],
        'cookie_policy' => ['label' => 'Cookie Policy', 'input' => 'url', 'tab' => 'legal', 'group' => 'Policy pages'],
        'cookie_consent' => ['label' => 'Cookie Consent', 'display' => 'Ask for cookie consent', 'input' => 'toggle', 'tab' => 'legal', 'group' => 'Cookie consent', 'help' => 'Show a consent banner; tracking tags only load after a visitor accepts.'],
        'cookie_message' => ['label' => 'Cookie Message', 'input' => 'text', 'tab' => 'legal', 'group' => 'Cookie consent', 'rules' => ['max:300'], 'placeholder' => 'We use cookies to understand how our site is used.'],

        // ── SEO & tracking ──
        'title_pattern' => ['label' => 'Title Pattern', 'input' => 'text', 'tab' => 'seo', 'rules' => ['max:120'], 'default' => '{page} | {site}', 'help' => 'Use {page} and {site}.'],
        'meta_description' => ['label' => 'Meta Description', 'input' => 'textarea', 'tab' => 'seo', 'rules' => ['max:320'], 'help' => 'Default search-result text for pages without their own.'],
        'noindex' => ['label' => 'Noindex', 'display' => 'Hide from search engines', 'input' => 'toggle', 'tab' => 'seo', 'help' => 'Hide the whole site from search engines.'],
        'noindex_while_draft' => ['label' => 'Noindex While Draft', 'display' => 'Hide until the site is live', 'input' => 'toggle', 'tab' => 'seo', 'default' => '1', 'help' => 'Keep search engines away until the site is live.'],
        'canonical_host' => ['label' => 'Canonical Host', 'display' => 'Preferred address', 'input' => 'select', 'tab' => 'seo', 'options' => ['apex' => 'Without www (example.com)', 'www' => 'With www (www.example.com)'], 'default' => 'apex'],
        'search_console_token' => ['label' => 'Search Console Token', 'input' => 'text', 'tab' => 'seo', 'rules' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{10,100}$/'], 'help' => 'The content value of Google\'s verification meta tag.'],
        'sitemap' => ['label' => 'Sitemap', 'display' => 'Publish a sitemap', 'input' => 'toggle', 'tab' => 'seo', 'default' => '1', 'help' => 'Publish /sitemap.xml for search engines.'],
        'ga4_id' => ['label' => 'Ga4 Id', 'display' => 'Google Analytics (GA4) ID', 'input' => 'text', 'tab' => 'seo', 'group' => 'Tracking', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]{4,12}$/i'], 'placeholder' => 'G-XXXXXXX'],
        'plausible_domain' => ['label' => 'Plausible Domain', 'display' => 'Plausible site domain', 'input' => 'text', 'tab' => 'seo', 'group' => 'Tracking', 'rules' => ['nullable', 'regex:/^[a-z0-9.\-]+\.[a-z]{2,}$/i'], 'placeholder' => 'yourbusiness.com'],
        'meta_pixel_id' => ['label' => 'Meta Pixel Id', 'display' => 'Meta Pixel ID', 'input' => 'text', 'tab' => 'seo', 'group' => 'Tracking', 'rules' => ['nullable', 'regex:/^[0-9]{6,20}$/']],
        'email_sender_name' => ['label' => 'Email Sender Name', 'input' => 'text', 'tab' => 'seo', 'group' => 'Emails to customers', 'rules' => ['max:80'], 'help' => 'The "from" name on booking, order and invoice emails.'],
        'reply_to' => ['label' => 'Reply To', 'display' => 'Reply-to address', 'input' => 'email', 'tab' => 'seo', 'group' => 'Emails to customers', 'rules' => ['nullable', 'email', 'max:190'], 'help' => 'Where customer replies go.'],

        // ── Locale & status ──
        'language' => ['label' => 'Language', 'input' => 'select', 'tab' => 'locale', 'options' => ['en-GB' => 'English (UK)', 'en-US' => 'English (US)', 'en-IE' => 'English (Ireland)', 'cy-GB' => 'Welsh', 'fr-FR' => 'French', 'es-ES' => 'Spanish', 'de-DE' => 'German', 'pt-PT' => 'Portuguese', 'pl-PL' => 'Polish'], 'default' => 'en-GB'],
        'timezone' => ['label' => 'Timezone', 'input' => 'select', 'tab' => 'locale', 'options' => 'timezones', 'default' => 'Europe/London'],
        'date_format' => ['label' => 'Date Format', 'input' => 'select', 'tab' => 'locale', 'options' => ['j M Y' => '29 Sep 2026', 'd/m/Y' => '29/09/2026', 'l j F Y' => 'Tuesday 29 September 2026', 'M j, Y' => 'Sep 29, 2026', 'Y-m-d' => '2026-09-29'], 'default' => 'j M Y'],
        'launch_date' => ['label' => 'Launch Date', 'input' => 'date', 'tab' => 'locale', 'group' => 'Status', 'rules' => ['nullable', 'date']],
        'maintenance' => ['label' => 'Maintenance', 'display' => 'Maintenance mode', 'input' => 'toggle', 'tab' => 'locale', 'group' => 'Status', 'help' => 'Visitors see a "back soon" page; you can still edit and preview.'],
        'maintenance_message' => ['label' => 'Maintenance Message', 'input' => 'textarea', 'tab' => 'locale', 'group' => 'Status', 'rules' => ['max:500'], 'placeholder' => 'We\'re making a few improvements — back very soon.'],

        // ── Assistant ──
        'assistant_name' => ['label' => 'Assistant Name', 'input' => 'text', 'tab' => 'assistant', 'rules' => ['max:60'], 'placeholder' => 'Grace'],
        'assistant_greeting' => ['label' => 'Assistant Greeting', 'input' => 'textarea', 'tab' => 'assistant', 'rules' => ['max:300'], 'placeholder' => 'Hi! How can I help you today?'],
        'assistant_tone' => ['label' => 'Assistant Tone', 'input' => 'select', 'tab' => 'assistant', 'options' => ['friendly' => 'Friendly', 'professional' => 'Professional', 'warm' => 'Warm & caring', 'playful' => 'Playful', 'concise' => 'Short & to the point'], 'default' => 'friendly', 'help' => 'Also used when the AI writes content for your site.'],
        'assistant_actions' => ['label' => 'Assistant Actions', 'input' => 'text', 'tab' => 'assistant', 'rules' => ['max:200'], 'placeholder' => 'bookings, enquiries', 'help' => 'What the assistant may help visitors do, e.g. bookings, enquiries, quotes.'],
        'assistant_handover' => ['label' => 'Assistant Handover', 'input' => 'text', 'tab' => 'assistant', 'rules' => ['max:190'], 'help' => 'Who to pass people to when it can\'t help (a name, email or number).'],
    ],

    'repeaters' => [
        'phones' => ['prefix' => 'Phone', 'tab' => 'contact', 'title' => 'Phone numbers', 'add' => '+ Add phone number',
            'fields' => [
                'label' => ['label' => 'Label', 'input' => 'text', 'placeholder' => 'Office', 'rules' => ['max:60']],
                'value' => ['label' => 'Value', 'input' => 'tel', 'placeholder' => '+44 20 7946 0000', 'required' => true, 'rules' => ['max:40', 'regex:/^[0-9+().\-\s\/ext]{3,40}$/i']],
            ]],
        'emails' => ['prefix' => 'Email', 'tab' => 'contact', 'title' => 'Other email addresses', 'add' => '+ Add email address',
            'fields' => [
                'label' => ['label' => 'Label', 'input' => 'text', 'placeholder' => 'Bookings', 'rules' => ['max:60']],
                'value' => ['label' => 'Value', 'input' => 'email', 'placeholder' => 'bookings@yourbusiness.com', 'required' => true, 'rules' => ['email', 'max:190']],
            ]],
        'closures' => ['prefix' => 'Closure', 'tab' => 'hours', 'title' => 'Bank holidays & special closures', 'add' => '+ Add date',
            'fields' => [
                'date' => ['label' => 'Date', 'input' => 'date', 'required' => true, 'rules' => ['date']],
                'hours' => ['label' => 'Hours', 'input' => 'text', 'placeholder' => 'Closed', 'rules' => ['max:60']],
                'note' => ['label' => 'Note', 'input' => 'text', 'placeholder' => 'Christmas Day', 'rules' => ['max:120']],
            ]],
        'accreditations' => ['prefix' => 'Accreditation', 'tab' => 'legal', 'title' => 'Regulators & accreditations', 'add' => '+ Add accreditation',
            'fields' => [
                'body' => ['label' => 'Body', 'input' => 'select', 'options' => ['CQC' => 'CQC', 'Gas Safe' => 'Gas Safe', 'NICEIC' => 'NICEIC', 'FCA' => 'FCA', 'SRA' => 'SRA', 'Ofsted' => 'Ofsted', 'Charity Commission' => 'Charity Commission', 'Other' => 'Other'], 'required' => true],
                'number' => ['label' => 'Number', 'input' => 'text', 'rules' => ['max:60']],
                'badge' => ['label' => 'Badge', 'input' => 'image'],
            ]],
    ],

    // Platform-controlled settings kept OUT of the component (never in the public
    // content payload; Properties page only): custom scripts (premium plans,
    // owners/admins) and the generated icon set.
    'scripts' => ['head' => 'site.custom_head', 'body' => 'site.custom_body'],
    'icons_attr' => 'site.icons',
    'icon_sizes' => [32, 48, 180, 192, 512],
];
