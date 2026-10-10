<?php

namespace App\Modules\Events\Models;

use App\Models\Media;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An event a site publishes — free RSVP and/or paid tickets.
 * Times are stored in the app timezone (UTC); `timezone` is the event's
 * local zone, used for display and the calendar invite.
 */
class Event extends Model
{
    use HasUlids;

    public const STATUSES = ['draft', 'published', 'cancelled'];

    protected $table = 'site_events';

    protected $fillable = [
        'site_id', 'title', 'slug', 'summary', 'description', 'image', 'starts_at', 'ends_at', 'timezone',
        'venue_name', 'venue_address', 'online_url', 'capacity', 'status', 'settings', 'reminder_sent_at', 'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
        'settings' => 'array',
        'reminder_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Event $event) {
            Ticket::where('event_id', $event->id)->delete();
            TicketOrder::where('event_id', $event->id)->delete();
            $event->ticketTypes()->delete();
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('created_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** A slug unique within the site, derived from $base. */
    public static function uniqueSlug(string $siteId, string $base, ?string $exceptId = null): string
    {
        $root = Str::slug($base) ?: 'event';
        $slug = $root;
        $i = 2;
        while (static::where('site_id', $siteId)->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            $slug = $root.'-'.$i++;
        }

        return $slug;
    }

    /** Start time in the event's own timezone. */
    public function localStart(): Carbon
    {
        return $this->starts_at->copy()->setTimezone($this->safeTimezone());
    }

    public function localEnd(): ?Carbon
    {
        return $this->ends_at?->copy()->setTimezone($this->safeTimezone());
    }

    public function safeTimezone(): string
    {
        return in_array($this->timezone, timezone_identifiers_list(), true) ? $this->timezone : 'UTC';
    }

    /** "Saturday 14 March 2026, 7:00 pm – 9:30 pm (Europe/London)". */
    public function whenLabel(): string
    {
        $start = $this->localStart();
        $end = $this->localEnd();
        $out = $start->format('l j F Y, g:i a');
        if ($end) {
            $out .= ' – '.($end->isSameDay($start) ? $end->format('g:i a') : $end->format('l j F Y, g:i a'));
        }

        return $out.' ('.$this->safeTimezone().')';
    }

    public function imageUrl(): string
    {
        return $this->image ? Media::resolveAbsolute($this->site_id, $this->image) : '';
    }

    public function isPast(): bool
    {
        return ($this->ends_at ?? $this->starts_at)->isPast();
    }

    public function venueLabel(): string
    {
        return trim(implode(', ', array_filter([$this->venue_name, $this->venue_address])));
    }
}
