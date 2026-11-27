<?php

declare(strict_types=1);

namespace App\Helpers;

class CookieHelper
{
    /**
     * Establece una cookie con opciones seguras (SameSite=Lax y HttpOnly configurable).
     */
    public static function set(
        string $name,
        string $value,
        int $days = 30,
        string $path = '/',
        bool $httpOnly = true
    ): bool {
        $expires = time() + ($days * 86400);
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        $_COOKIE[$name] = $value;

        return setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $path,
            'secure'   => $isSecure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Lee el valor de una cookie o devuelve un valor por defecto si no existe.
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Programa la caducidad corta de una cookie existente (en segundos desde ahora).
     */
    public static function expire(
        string $name,
        int $secondsFromNow = 60,
        string $path = '/',
        bool $httpOnly = true
    ): bool {
        if (!isset($_COOKIE[$name])) {
            return false;
        }

        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        return setcookie($name, (string) $_COOKIE[$name], [
            'expires'  => time() + max(1, $secondsFromNow),
            'path'     => $path,
            'secure'   => $isSecure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Elimina una cookie de inmediato expirándola en el pasado y limpiando $_COOKIE.
     */
    public static function delete(string $name, string $path = '/'): bool
    {
        unset($_COOKIE[$name]);
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        return setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Devuelve todas las cookies actuales excepto la cookie de sesión de PHP.
     */
    public static function all(): array
    {
        $cookies = $_COOKIE;
        unset($cookies[session_name()]);
        return $cookies;
    }
}