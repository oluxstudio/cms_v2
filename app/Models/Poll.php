<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** An owner-authored poll: one question, its options, and visitor votes. */
class Poll extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'question', 'slug', 'is_open', 'multiple', 'ends_at', 'sort'];

    protected $casts = ['is_open' => 'boolean', 'multiple' => 'boolean', 'ends_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /** How many PEOPLE voted (multi-choice polls cast several votes each). */
    public function voterCount(): int
    {
        return $this->votes()->distinct('voter_hash')->count('voter_hash');
    }

    /** Currently accepting votes? (open flag + optional end date) */
    public function acceptsVotes(): bool
    {
        return $this->is_open && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** @return array<int,array{id:string,label:string,votes:int,share:int}> */
    public function results(): array
    {
        $options = $this->options()->withCount('votes')->get();
        $total = max(1, $options->sum('votes_count'));

        return $options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'votes' => $o->votes_count,
            'share' => (int) round($o->votes_count / $total * 100),
        ])->all();
    }

    public static function slugFor(string $siteId, string $question): string
    {
        $base = Str::slug(Str::limit($question, 60, '')) ?: 'poll';
        $slug = $base;
        $i = 2;
        while (static::where('site_id', $siteId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
