<?php

namespace App\Models;

use App\Templates\ArrayTemplate;
use App\Templates\TemplateContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable version of a catalog template. The `payload` holds everything the
 * installer needs: { theme, fonts, css, pages } with absolute asset URLs baked in.
 */
class TemplateVersion extends Model
{
    use HasUlids;

    protected $fillable = ['template_id', 'version', 'manifest', 'payload', 'status'];

    protected $casts = [
        'manifest' => 'array',
        'payload' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** Build a TemplateContract the TemplateService/Installer can apply. */
    public function toContract(): TemplateContract
    {
        $payload = $this->payload ?? [];
        $manifest = $this->manifest ?? [];
        $tpl = $this->template;

        return new ArrayTemplate(
            meta: array_merge($manifest, [
                'key' => $tpl?->slug ?? ('template-'.$this->template_id),
                'version' => $this->version,
            ]),
            pages: $payload['pages'] ?? [],
            theme: $payload['theme'] ?? [],
            thumbnailUrl: $tpl?->thumbnail_url,
        );
    }
}
