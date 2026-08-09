<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Site;
use App\Services\MediaStore;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Seeds the portfolio "About" section as CMS content on a site so it becomes
 * editable: home-page attributes for the scalar copy + four collections
 * (stats, team, faq, collage) for the repeating lists. Idempotent — safe to
 * re-run. The Nuxt site reads these via useCmsContent() (attr + collection).
 *
 *   php artisan cms:seed-about deve-site
 *   php artisan cms:seed-about deve-site --assets=/tmp/about-collage
 *
 * With --assets, collage source files found there are uploaded to the site's
 * media library and each collage item's url is set to the stored CMS URL;
 * files over the 50 MB cap are skipped + logged (the client keeps its bundled
 * fallback for those).
 */
class SeedAboutSection extends Command
{
    protected $signature = 'cms:seed-about {site=deve-site} {--assets= : Directory holding the collage source files}';

    protected $description = 'Seed the About section (page attributes + collections) onto a CMS site';

    /** Scalar copy → home-page attributes (read by useCmsContent attr()). */
    private const ATTRS = [
        'about_subtitle' => 'About Us',
        'about_intro_subtitle' => 'From first sketch to final click, we help your vision come alive online.',
        'about_intro_headline' => 'Turning Bright Ideas into',
        'about_intro_headline_accent' => 'Beautiful Websites',
        'about_intro_body' => "A modern web studio is a creative and technical partner that designs and builds high-quality, custom digital experiences. We're a small, specialised team focused on creating websites and digital products that are visually striking, high-performing, and conversion-driven.",
        'about_intro_cta' => 'See our services',
        'about_mission' => 'We are on a mission to help businesses reach their full potential through smarter and more purposeful web experiences built for the modern world.',
        'about_team_label' => 'Team',
        'about_team_header' => 'Meet the Studio',
        'about_team_sub' => 'Small team. Big impact. Direct access to the people building your project.',
        'about_faq_label' => 'Got questions?',
        'about_faq_desc' => 'Clear answers to common questions about our studio, process, and services.',
        'about_cta_heading' => "Let's build something great together",
        'about_cta_body' => "Tell us about your project and we'll get back to you within 24 hours.",
        'about_cta_primary' => 'Get in touch',
        'about_cta_secondary' => 'See our services',
    ];

    private const STATS = [
        ['value' => '50+', 'label' => 'Projects Delivered'],
        ['value' => '30+', 'label' => 'Happy Clients'],
        ['value' => '5+', 'label' => 'Years Experience'],
        ['value' => '100%', 'label' => 'On-Time Delivery'],
    ];

    private const TEAM = [
        ['name' => 'Oluwaseun', 'role' => 'Founder & Lead Developer', 'initials' => 'O'],
        ['name' => 'Design Lead', 'role' => 'UI / UX Designer', 'initials' => 'DL'],
        ['name' => 'Dev Support', 'role' => 'Frontend Engineer', 'initials' => 'DS'],
    ];

    private const FAQ = [
        ['group' => 'general', 'question' => 'What is Olux Studio?', 'answer' => 'Olux Studio is a specialist web design and development studio based in Blackburn, Lancashire. We build bespoke websites and digital experiences for businesses, startups, and creatives who want to stand out online.'],
        ['group' => 'general', 'question' => 'Do you work with clients remotely?', 'answer' => 'Absolutely. Most of our clients are remote. We communicate via video call, email, and shared project boards — no need to be local to work with us.'],
        ['group' => 'general', 'question' => 'What industries do you work with?', 'answer' => 'We work across a wide range of industries including retail, professional services, hospitality, creative agencies, and technology startups. If you have a website need, we can help.'],
        ['group' => 'process', 'question' => 'How long does a typical project take?', 'answer' => 'Most projects land between 2–6 weeks. A simple landing page can be ready in 1–2 weeks, while a full multi-page site with CMS integration typically takes 4–6 weeks. We agree on a timeline before any work begins.'],
        ['group' => 'process', 'question' => 'What do you need from me to get started?', 'answer' => "A brief covering your goals, target audience, and any sites you admire. From there we'll schedule a discovery call to scope the project properly before sending a proposal."],
        ['group' => 'process', 'question' => 'How many rounds of revisions do I get?', 'answer' => 'All plans include at least 2 rounds of design revisions. We work collaboratively throughout, so by the time we reach revisions the design is usually very close to final.'],
        ['group' => 'technical', 'question' => 'What tech stack do you use?', 'answer' => "We primarily build with Nuxt 3, Vue, and Tailwind CSS for custom sites. For CMS-driven projects we use headless options like Sanity or Contentful. We're tech-agnostic and can work within your existing stack if needed."],
        ['group' => 'technical', 'question' => 'Will I own the code?', 'answer' => "Yes — full code ownership is transferred to you on project completion. We don't use proprietary builders or lock you into our systems."],
        ['group' => 'technical', 'question' => 'Can you work with my existing website?', 'answer' => "Yes. We offer redesigns, partial rebuilds, and performance audits. Get in touch with details of your current site and we'll advise on the best path forward."],
        ['group' => 'pricing', 'question' => 'How much does a website cost?', 'answer' => 'Our Starter plan begins at £799 for up to 3 pages. Full multi-page sites start at £1,999. Custom apps and e-commerce builds are scoped individually. See our Services section for full pricing details.'],
        ['group' => 'pricing', 'question' => 'Do you offer payment plans?', 'answer' => 'Yes — we typically split projects into a 50% deposit upfront and 50% on completion. For larger projects we can arrange milestone-based payment schedules.'],
        ['group' => 'pricing', 'question' => 'What happens if I need changes after launch?', 'answer' => 'All plans include a post-launch support window (30 days on Starter, 3 months on Growth). After that, changes are covered under a monthly retainer or billed at our standard hourly rate.'],
    ];

    /** Collage items: [type, source filename (under --assets), alt] or a text card. */
    private const COLLAGE = [
        ['type' => 'video', 'file' => 'solo.mp4', 'alt' => 'Team at work'],
        ['type' => 'video', 'file' => 'code.mp4', 'alt' => 'Development in action'],
        ['type' => 'image', 'file' => 'port1.png', 'alt' => 'Studio work'],
        ['type' => 'image', 'file' => 'port4.png', 'alt' => 'Design thinking'],
        ['type' => 'image', 'file' => 'port2.png', 'alt' => 'Design process'],
        ['type' => 'video', 'file' => '0_Abstract_Geometric_3840x2160.mp4', 'alt' => 'Abstract motion'],
        ['type' => 'image', 'file' => 'port15.png', 'alt' => 'Pattern design'],
        ['type' => 'image', 'file' => 'port3.png', 'alt' => 'Web development'],
        ['type' => 'image', 'file' => 'blog11.png', 'alt' => 'Colour in design'],
        ['type' => 'image', 'file' => 'port12.png', 'alt' => 'Project work'],
        ['type' => 'text', 'label' => 'Our Mission', 'text' => 'Turning bright ideas into beautiful, high-performing web experiences.'],
        ['type' => 'text', 'label' => 'Our Approach', 'text' => 'Every pixel crafted with purpose. Every line of code built to last.'],
    ];

    public function handle(MediaStore $mediaStore): int
    {
        $site = Site::where('name', $this->argument('site'))->first();
        if (! $site) {
            $this->error("Site “{$this->argument('site')}” not found.");

            return self::FAILURE;
        }

        // 1 · Home-page attributes (scalar copy).
        $home = $site->pages()->where('url', '/')->first()
            ?? $site->pages()->create(['name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
        foreach (self::ATTRS as $key => $value) {
            $home->setAttr($key, $value);
        }
        $this->info('✓ Set '.count(self::ATTRS).' home-page attributes.');

        // 2 · List collections (idempotent by slug).
        $this->syncCollection($site, 'Stats', self::STATS,
            [['key' => 'value', 'label' => 'Value', 'type' => 'text'], ['key' => 'label', 'label' => 'Label', 'type' => 'text']]);
        $this->syncCollection($site, 'Team', self::TEAM,
            [['key' => 'name', 'label' => 'Name', 'type' => 'text'], ['key' => 'role', 'label' => 'Role', 'type' => 'text'], ['key' => 'initials', 'label' => 'Initials', 'type' => 'text']]);
        $this->syncCollection($site, 'FAQ', self::FAQ,
            [['key' => 'group', 'label' => 'Group', 'type' => 'select', 'options' => ['general', 'process', 'technical', 'pricing']], ['key' => 'question', 'label' => 'Question', 'type' => 'text'], ['key' => 'answer', 'label' => 'Answer', 'type' => 'textarea']]);

        // 3 · Collage — resolve media (upload from --assets when available).
        $this->syncCollection($site, 'Collage', $this->collageItems($site, $mediaStore),
            [['key' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['image', 'video', 'text']], ['key' => 'url', 'label' => 'URL', 'type' => 'url'], ['key' => 'alt', 'label' => 'Alt', 'type' => 'text'], ['key' => 'label', 'label' => 'Label', 'type' => 'text'], ['key' => 'text', 'label' => 'Text', 'type' => 'textarea']]);

        $this->newLine();
        $this->info("About section seeded onto “{$site->name}”.");

        return self::SUCCESS;
    }

    /** Create/refresh a public collection and replace its items. */
    private function syncCollection(Site $site, string $name, array $items, array $fields): void
    {
        $collection = Collection::updateOrCreate(
            ['site_id' => $site->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'type' => 'list', 'fields' => $fields, 'is_public' => true, 'allow_submit' => false],
        );
        $collection->items()->delete();
        foreach ($items as $data) {
            $collection->items()->create(['site_id' => $site->id, 'data' => $data, 'status' => 'published']);
        }
        $this->info("✓ Collection “{$collection->slug}” — ".count($items).' items.');
    }

    /** Build collage item rows, uploading source media when an --assets dir is given. */
    private function collageItems(Site $site, MediaStore $mediaStore): array
    {
        $dir = $this->option('assets');
        $urls = [];

        if ($dir && File::isDirectory($dir)) {
            foreach (self::COLLAGE as $item) {
                if (($item['type'] ?? '') === 'text') {
                    continue;
                }
                $path = rtrim($dir, '/').'/'.$item['file'];
                if (! File::exists($path)) {
                    $this->warn("  · collage source missing: {$item['file']}");

                    continue;
                }
                if (File::size($path) > 50 * 1024 * 1024) {
                    $this->warn("  · skipped (over 50 MB): {$item['file']} — client keeps bundled fallback");

                    continue;
                }
                $media = $mediaStore->store($site, new UploadedFile($path, $item['file'], null, null, true));
                $urls[$item['file']] = $media->publicUrl();
            }
            $this->info('✓ Uploaded '.count($urls).' collage media file(s).');
        } else {
            $this->warn('No --assets dir given — collage items created without CMS media URLs (client uses bundled fallback).');
        }

        return array_map(function ($item) use ($urls) {
            if (($item['type'] ?? '') === 'text') {
                return ['type' => 'text', 'url' => '', 'alt' => '', 'label' => $item['label'], 'text' => $item['text']];
            }

            return [
                'type' => $item['type'],
                'url' => $urls[$item['file']] ?? '',
                'alt' => $item['alt'] ?? '',
                'label' => '',
                'text' => '',
            ];
        }, self::COLLAGE);
    }
}
