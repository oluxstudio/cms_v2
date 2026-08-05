<?php

namespace App\Services\Estimator;

/**
 * Tiny SAFE arithmetic evaluator for admin-authored estimator formulas.
 * Supports numbers, field identifiers, + - * / and parentheses — nothing
 * else, so no eval() and no way to run code.
 */
class Formula
{
    /** Tokenize an expression. Returns null when it contains anything illegal. */
    private static function tokens(string $expr): ?array
    {
        if (! preg_match_all('/\s*(\d+\.?\d*|[a-z_][a-z0-9_]*|[+\-*\/()])\s*/i', $expr, $m, PREG_PATTERN_ORDER)
            || implode('', array_map('trim', $m[0])) !== preg_replace('/\s+/', '', $expr)) {
            return null; // stray characters present
        }

        return $m[1];
    }

    /** Identifiers referenced by the expression (for validation/UI). */
    public static function identifiers(string $expr): array
    {
        return array_values(array_unique(array_filter(self::tokens($expr) ?? [],
            fn ($t) => preg_match('/^[a-z_]/i', $t))));
    }

    /** Is the expression syntactically sound and only using known keys? */
    public static function validate(string $expr, array $knownKeys): ?string
    {
        if (self::tokens($expr) === null) {
            return 'The formula contains characters that are not allowed — use numbers, field keys, + - * / and ( ).';
        }
        $unknown = array_diff(self::identifiers($expr), $knownKeys);
        if ($unknown !== []) {
            return 'Unknown field key(s): '.implode(', ', $unknown).'.';
        }
        if (self::evaluate($expr, array_fill_keys($knownKeys, 1)) === null) {
            return 'The formula does not compute — check operators and parentheses.';
        }

        return null;
    }

    /** Evaluate with the given identifier values. Null on any error. */
    public static function evaluate(string $expr, array $vars): ?float
    {
        $tokens = self::tokens($expr);
        if ($tokens === null || $tokens === []) {
            return null;
        }

        // Resolve identifiers → numbers; support unary minus by injecting 0.
        $resolved = [];
        $prev = null;
        foreach ($tokens as $t) {
            if (preg_match('/^[a-z_]/i', $t)) {
                $t = (string) (float) ($vars[$t] ?? 0);
            }
            if ($t === '-' && ($prev === null || in_array($prev, ['+', '-', '*', '/', '('], true))) {
                $resolved[] = '0';
            }
            $resolved[] = $t;
            $prev = $t;
        }

        // Shunting-yard → RPN.
        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $out = [];
        $ops = [];
        foreach ($resolved as $t) {
            if (is_numeric($t)) {
                $out[] = (float) $t;
            } elseif (isset($prec[$t])) {
                while ($ops && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                    $out[] = array_pop($ops);
                }
                $ops[] = $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while ($ops && end($ops) !== '(') {
                    $out[] = array_pop($ops);
                }
                if (array_pop($ops) !== '(') {
                    return null; // unbalanced
                }
            } else {
                return null;
            }
        }
        while ($ops) {
            $op = array_pop($ops);
            if ($op === '(') {
                return null;
            }
            $out[] = $op;
        }

        // Evaluate RPN.
        $stack = [];
        foreach ($out as $t) {
            if (is_float($t)) {
                $stack[] = $t;

                continue;
            }
            $b = array_pop($stack);
            $a = array_pop($stack);
            if ($a === null || $b === null) {
                return null;
            }
            $stack[] = match ($t) {
                '+' => $a + $b,
                '-' => $a - $b,
                '*' => $a * $b,
                '/' => $b == 0.0 ? 0.0 : $a / $b,
            };
        }

        return count($stack) === 1 ? (float) $stack[0] : null;
    }
}
