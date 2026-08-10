<?php

namespace App\Livewire\Concerns;

/**
 * Per-site, per-page layout switching (grid / list / compact) for admin content lists.
 *
 * The choice is persisted on the site via the site_attributes table
 * (Site::getAttr/setAttr) under the key "layout:{page}", so it is shared by the
 * whole team and survives reloads. Each page opens in a smart default until the
 * user overrides it.
 *
 * Host component requirements: a public Site $site property, and a call to
 * initLayout() from its own mount() AFTER $site has been assigned.
 */
trait WithLayoutMode
{
    public string $viewMode = 'list';

    public string $layoutPage = '';

    public array $layoutModes = ['grid', 'list', 'compact'];

    public function initLayout(string $page, string $default = 'list', array $modes = ['grid', 'list', 'compact']): void
    {
        $this->layoutPage = $page;
        $this->layoutModes = $modes;

        $mode = $this->site->getAttr("layout:{$page}", $default);
        $this->viewMode = in_array($mode, $modes, true) ? $mode : $default;
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, $this->layoutModes, true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->site->setAttr("layout:{$this->layoutPage}", $mode);
    }
}
