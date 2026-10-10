<?php

namespace App\Modules\Events;

use App\Models\Site;
use App\Modules\Events\Models\Event;

/** Builds an RFC 5545 .ics (add-to-calendar) for an event. */
class CalendarInvite
{
    public static function make(Event $event, Site $site): string
    {
        $fmt = fn ($dt) => $dt->copy()->utc()->format('Ymd\THis\Z');
        $end = $event->ends_at ?? $event->starts_at->copy()->addHours(2);
        $location = $event->venueLabel() ?: (string) $event->online_url;
        $desc = trim(($event->summary ?: '').($event->online_url ? "\n\nJoin online: ".$event->online_url : ''));
        $status = $event->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Olux Studio//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$event->id.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'olux.studio'),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($event->starts_at),
            'DTEND:'.$fmt($end),
            'SUMMARY:'.self::escape($event->title),
            'STATUS:'.$status,
        ];
        if ($location !== '') {
            $lines[] = 'LOCATION:'.self::escape($location);
        }
        if ($desc !== '') {
            $lines[] = 'DESCRIPTION:'.self::escape($desc);
        }
        if ($event->online_url) {
            $lines[] = 'URL:'.self::escape((string) $event->online_url);
        }
        $lines[] = 'ORGANIZER;CN='.self::escape(ucwords(str_replace('-', ' ', $site->name))).':mailto:'.config('mail.from.address', 'noreply@example.com');
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    private static function escape(string $v): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $v);
    }

    /** Fold lines longer than 75 octets. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            // don't split a multibyte character
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
