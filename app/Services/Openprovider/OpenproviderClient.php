<?php

namespace App\Services\Openprovider;

use App\Models\EmailProviderLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Authenticated Openprovider REST calls (https://api.openprovider.eu/v1):
 * bearer token cached (shared with the domain registrar) and refreshed once
 * on a 401. Every call can be logged for support with secrets redacted.
 */
class OpenproviderClient
{
    public const TOKEN_CACHE_KEY = 'openprovider.token';

    private const SECRET_KEYS = ['password', 'password_confirmation', 'token', 'private_key', 'auth_code'];

    public function __construct(private array $config = []) {}

    /**
     * @param  array{action?:string, account_id?:?string}  $log  when given, the call is written to email_provider_logs
     */
    public function send(string $method, string $path, array $payload = [], array $log = [], bool $retried = false): array
    {
        $started = microtime(true);
        $status = null;
        $json = null;
        try {
            $res = $this->http()->withToken($this->token())->{$method}($this->url($path), $payload);
            $status = $res->status();
            if ($status === 401 && ! $retried) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                return $this->send($method, $path, $payload, $log, retried: true);
            }
            $json = $res->json();
            if (! $res->successful() || (int) ($json['code'] ?? -1) !== 0) {
                throw new RuntimeException('Openprovider: '.($json['desc'] ?? 'HTTP '.$status));
            }

            return (array) ($json['data'] ?? []);
        } finally {
            if ($log) {
                $this->record($log, $method, $path, $payload, $json, $status, $started);
            }
        }
    }

    private function record(array $log, string $method, string $path, array $payload, $json, ?int $status, float $started): void
    {
        try {
            EmailProviderLog::create([
                'account_id' => $log['account_id'] ?? null,
                'provider' => 'openprovider',
                'action' => $log['action'] ?? 'call',
                'method' => strtoupper($method),
                'path' => $path,
                'request' => self::redact($payload),
                'response' => is_array($json) ? self::redact($json) : null,
                'http_status' => $status,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        } catch (Throwable $e) {
            report($e); // logging must never break a provider call
        }
    }

    /** Replace secret values (passwords, tokens, keys) anywhere in a payload. */
    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::redact($v);
            } elseif (is_string($k) && in_array(strtolower($k), self::SECRET_KEYS, true) && $v !== null && $v !== '') {
                $data[$k] = '[redacted]';
            }
        }

        return $data;
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, (int) ($this->config['token_ttl'] ?? 43200), function () {
            $username = (string) ($this->config['username'] ?? '');
            $password = (string) ($this->config['password'] ?? '');
            if ($username === '' || $password === '') {
                throw new RuntimeException('Openprovider credentials are not configured (OPENPROVIDER_USER / OPENPROVIDER_PASS).');
            }
            $res = $this->http()->post($this->url('/auth/login'), ['username' => $username, 'password' => $password, 'ip' => '0.0.0.0']);
            $json = $res->json();
            if (! $res->successful() || (int) ($json['code'] ?? -1) !== 0 || blank($json['data']['token'] ?? null)) {
                throw new RuntimeException('Openprovider login failed: '.($json['desc'] ?? 'HTTP '.$res->status()));
            }

            return (string) $json['data']['token'];
        });
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) ($this->config['timeout'] ?? 20))->acceptJson()->asJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['url'] ?? ''), '/').$path;
    }
}
