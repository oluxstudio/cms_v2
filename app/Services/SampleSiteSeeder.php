<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Component;
use App\Models\Form;
use App\Models\Page;
use App\Models\Site;

/**
 * Scaffolds a realistic starter into a fresh site so onboarding users see a
 * populated product instead of a blank canvas: a Home + About page, a Hero and
 * About component, a Testimonials collection (3 grouped components) and a
 * Contact form wired to the CRM.
 */
class SampleSiteSeeder
{
    public function seed(Site $site): void
    {
        $author = $site->user?->name ?? 'Olux';
        $siteTitle = ucwords(str_replace('-', ' ', $site->name));

        $home = $site->pages()->firstOrCreate(['url' => '/'], ['name' => 'Home', 'keywords' => '', 'is_published' => true]);
        $about = $site->pages()->firstOrCreate(['url' => '/about'], ['name' => 'About', 'keywords' => '', 'is_published' => true]);

        // ── Hero on Home ──────────────────────────────────────────
        $hero = $this->component($site, $author, 'Hero', [
            ['heading', 'text', "Welcome to {$siteTitle}"],
            ['subheading', 'text', 'The friendly team that gets it done — book, ask or say hello.'],
            ['cta_label', 'text', 'Get in touch'],
        ]);
        $home->components()->syncWithoutDetaching([$hero->id => ['order' => 0]]);

        // ── About on About page ───────────────────────────────────
        $aboutCmp = $this->component($site, $author, 'About', [
            ['title', 'text', 'About us'],
            ['body', 'text', "We're a small team that cares about doing great work. Edit this text to tell your own story."],
        ]);
        $about->components()->syncWithoutDetaching([$aboutCmp->id => ['order' => 0]]);

        // ── Testimonials collection (grouped components) ───────────
        $collection = $site->collections()->create([
            'name' => 'Testimonials', 'slug' => 'testimonials', 'type' => 'list', 'is_public' => true,
            'fields' => [['key' => 'quote', 'type' => 'textarea'], ['key' => 'author', 'type' => 'text']],
        ]);
        foreach ([
            ['Absolutely brilliant service from start to finish.', 'Sam R.'],
            ['Fast, friendly and exactly what we needed.', 'Priya K.'],
            ['Would recommend to anyone — five stars.', 'Tom B.'],
        ] as $i => [$quote, $who]) {
            $t = $this->component($site, $author, 'Testimonial '.($i + 1), [
                ['quote', 'text', $quote],
                ['author', 'text', $who],
            ], collectionId: $collection->id, collectionOrder: $i);
        }
        $home->collections()->syncWithoutDetaching([$collection->id => ['order' => 1]]);

        // ── Contact form (feeds the CRM) ──────────────────────────
        $site->forms()->firstOrCreate(['name' => 'contact'], [
            'title' => 'Contact us',
            'description' => 'Questions or bookings — drop us a line.',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
            ],
            'is_active' => true,
        ]);
    }

    /** Create a component with a set of [label, type, value] text nodes. */
    private function component(Site $site, string $author, string $name, array $nodes, ?string $collectionId = null, ?int $collectionOrder = null): Component
    {
        $component = Component::create([
            'site_id' => $site->id,
            'name' => $name,
            'author' => $author,
            'source' => 'app',
            'collection_id' => $collectionId,
            'collection_order' => $collectionOrder,
        ]);

        foreach ($nodes as $i => [$label, $type, $value]) {
            $component->nodes()->create([
                'label' => $label, 'type' => $type, 'value' => $value, 'parent' => '0', 'order' => $i,
            ]);
        }

        return $component;
    }
}
