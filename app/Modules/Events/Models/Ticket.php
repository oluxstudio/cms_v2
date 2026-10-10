<?php

namespace App\Modules\Events\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** One admission — an attendee with a unique door code. */
class Ticket extends Model
{
    use HasUlids;

    /** Unambiguous code alphabet (no 0/O, 1/I/L). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $table = 'event_tickets';

    protected $fillable = ['order_id', 'event_id', 'ticket_type_id', 'attendee_name', 'attendee_email', 'code', 'checked_in_at'];

    protected $casts = ['checked_in_at' => 'datetime'];

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'order_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /** Signed link to the hosted ticket page (shows the QR code). */
    public function url(string $siteName): string
    {
        return URL::signedRoute('public.events.ticket', ['siteName' => $siteName, 'code' => $this->code]);
    }

    /** QR code (SVG markup) encoding the door code. */
    public function qrSvg(int $size = 220): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($this->code);

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
