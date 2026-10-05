<?php

namespace App\Observers;

use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\PageAttribute;
use App\Models\Site;
use App\Support\SiteContentCache;
use Illuminate\Database\Eloquent\Model;

/** Any change to something the public content payload is built from retires that site's cached copy. */
class SiteContentObserver
{
    /** @var array<string,?string> component id → site id (per request) */
    private static array $componentSite = [];

    public function saved(Model $model): void
    {
        SiteContentCache::bump($this->siteId($model));
    }

    public function deleted(Model $model): void
    {
        SiteContentCache::bump($this->siteId($model));
    }

    private function siteId(Model $model): ?string
    {
        return match (true) {
            $model instanceof Site => $model->id,
            $model instanceof Node => self::$componentSite[$model->component_id] ??= Component::whereKey($model->component_id)->value('site_id'),
            $model instanceof PageAttribute => Page::whereKey($model->page_id)->value('site_id'),
            default => $model->site_id ?? null,
        };
    }
}
