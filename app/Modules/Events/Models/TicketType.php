<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A kind of ticket for an event (General, VIP, Free RSVP…). price_cents 0 = free. */
class TicketType extends Model
{
    use HasUlids;

    protected $table = 'event_ticket_types';

    protected $fillable = [
        'event_id', 'name', 'description', 'price_cents', 'quantity', 'sales_start', 'sales_end', 'max_per_order', 'sort',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'quantity' => 'integer',
        'sales_start' => 'datetime',
        'sales_end' => 'datetime',
        'max_per_order' => 'integer',
        'sort' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isFree(): bool
    {
        return (int) $this->price_cents === 0;
    }

    /** Within its sales window right now? */
    public function onSale(): bool
    {
        return (! $this->sales_start || $this->sales_start->isPast())
            && (! $this->sales_end || $this->sales_end->isFuture());
    }
}
