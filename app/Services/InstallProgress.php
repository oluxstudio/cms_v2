<?php

namespace App\Services;

use App\Models\Site;

/**
 * Live progress for a template install, read by the "Setting up your site"
 * screen (/connect polls it). Stored on the site as `template_install_progress`:
 * {percent, label, step, done, total}. Creating pages + their sections is the
 * long part, so it carries half the bar, split per page.
 */
class InstallProgress
{
    public const ATTR = 'template_install_progress';

    /** step => [start %, end %] */
    private const STEPS = [
        'pages' => [2, 55], 'layout' => [55, 65], 'features' => [65, 70], 'forms' => [70, 78],
        'collections' => [78, 86], 'linking' => [86, 92], 'booking' => [92, 95], 'theme' => [95, 99],
    ];

    public function __construct(private Site $site) {}

    /** Mark progress through a step (optionally $done of $total units, e.g. pages). */
    public function step(string $step, string $label, int $done = 0, int $total = 0): void
    {
        [$from, $to] = self::STEPS[$step] ?? [0, 0];
        $percent = $total > 0 ? $from + (int) round(($to - $from) * min($done, $total) / $total) : $from;
        $this->write(['percent' => $percent, 'label' => $label, 'step' => $step, 'done' => $done, 'total' => $total]);
    }

    public function finish(): void
    {
        $this->write(['percent' => 100, 'label' => 'Your site is ready', 'step' => 'done', 'done' => 0, 'total' => 0]);
    }

    private function write(array $state): void
    {
        $this->site->setAttr(self::ATTR, json_encode($state));
    }

    /** @return array{percent: int, label: string, step: string, done: int, total: int}|null */
    public static function read(Site $site): ?array
    {
        $state = json_decode((string) $site->getAttr(self::ATTR, ''), true);

        return is_array($state) ? $state + ['percent' => 0, 'label' => '', 'step' => '', 'done' => 0, 'total' => 0] : null;
    }
}
