<?php

namespace App\Support;

/**
 * Recent ERROR/CRITICAL entries from the end of laravel.log, grouped by
 * message so repeats collapse into one row with a count.
 */
class LogTail
{
    /**
     * @return list<array{message:string, level:string, count:int, last_at:string, trace:string}>
     */
    public static function errors(?string $path = null, int $bytes = 524288, int $limit = 50): array
    {
        $path ??= storage_path('logs/laravel.log');
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }
        $size = filesize($path);
        $fh = fopen($path, 'r');
        fseek($fh, max(0, $size - $bytes));
        $chunk = (string) stream_get_contents($fh);
        fclose($fh);

        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*\] [\w-]+\.[A-Z]+:)/m', $chunk) ?: [];
        $groups = [];
        foreach ($parts as $entry) {
            if (! preg_match('/^\[([^\]]+)\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)/s', $entry, $m)) {
                continue;
            }
            $lines = preg_split('/\R/', trim($m[3]));
            $message = mb_substr(preg_replace('/ \{"(exception|userId)".*$/', '', (string) $lines[0]), 0, 300);
            $key = md5($message);
            $groups[$key] ??= ['message' => $message, 'level' => $m[2], 'count' => 0, 'last_at' => $m[1], 'trace' => ''];
            $groups[$key]['count']++;
            $groups[$key]['last_at'] = $m[1];
            $groups[$key]['trace'] = implode("\n", array_slice($lines, 0, 20));
        }

        return array_slice(array_values(array_reverse($groups)), 0, $limit);
    }

    /** Distinct errors (by message) last seen since a moment, within the scanned tail. */
    public static function typesSince(\DateTimeInterface $since): int
    {
        return collect(self::errors(null, 1048576, 500))
            ->filter(fn ($g) => strtotime($g['last_at']) >= $since->getTimestamp())
            ->count();
    }
}
