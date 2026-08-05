<?php

namespace App\Support;

/**
 * ONE money formatter for the whole CMS — symbol and its position come
 * from config/currencies.php (£12.50 vs 12,50 €). Unknown codes fall
 * back to "12.50 XXX".
 */
class Money
{
    public static function format(int $cents, ?string $code, bool $free = false): string
    {
        if ($free && $cents === 0) {
            return 'Free';
        }
        $code = strtolower($code ?: 'gbp');
        $meta = config("currencies.$code");
        $amount = number_format($cents / 100, $meta['decimals'] ?? 2);

        if (! $meta) {
            return $amount.' '.strtoupper($code);
        }

        return $meta['position'] === 'after'
            ? $amount.' '.$meta['symbol']
            : $meta['symbol'].$amount;
    }

    public static function symbol(?string $code): string
    {
        return config('currencies.'.strtolower($code ?: 'gbp').'.symbol', strtoupper((string) $code));
    }

    /** code => "£ British Pound" options for selects. */
    public static function options(): array
    {
        return collect(config('currencies'))
            ->map(fn ($m) => $m['symbol'].' '.$m['label'])->all();
    }
}
