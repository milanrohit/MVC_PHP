<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Session
{
    private const NAME = 'mvc_session';

    /**
     * Starts the session with hardened cookie settings.
     *
     * @param bool|null $secure Force the Secure cookie flag on or off; null detects HTTPS
     *                          (including TLS terminated by a proxy or ingress).
     */
    public static function start(?bool $secure = null): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        \session_name(self::NAME);

        $started = \session_start([
            'cookie_lifetime' => 0,
            'cookie_path' => '/',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $secure ?? self::isHttps(),
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
        ]);

        if (!$started) {
            throw new RuntimeException('Unable to start the session.');
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        // Without an active session this write would be silently lost
        self::start();

        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Issues a new session ID and discards the old one.
     * Call this immediately after login and after any privilege change (prevents session fixation).
     */
    public static function regenerate(): void
    {
        self::start();

        if (!\session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate the session ID.');
        }
    }

    /**
     * Ends the session completely: data, server-side record and browser cookie. Call on logout.
     */
    public static function destroy(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        $cookie = \session_get_cookie_params();
        \setcookie(self::NAME, '', [
            'expires' => \time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);

        \session_destroy();
    }

    /**
     * X-Forwarded-Proto is only used to ADD the Secure flag, so a spoofed header
     * can never weaken the cookie (at worst it stops the browser sending it over HTTP).
     */
    private static function isHttps(): bool
    {
        $https = \strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $proto = \strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return ($https !== '' && $https !== 'off') || \str_starts_with($proto, 'https');
    }
}
