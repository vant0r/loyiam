<?php
/**
 * Oddiy fayl-bazli cache layer (JSON-based — XAVFSIZ)
 *
 * v3.0 — unserialize() PHP Object Injection vektori bo'lganligi uchun
 * to'liq olib tashlandi. Endi faqat JSON ishlatamiz (gadget chain RCE riski yo'q).
 *
 * Foydalanish:
 *   $tariffs = Cache::remember('tariffs', 1800, fn() => db()->fetchAll(...));
 *   Cache::forget('tariffs');
 *   Cache::flush();
 */

class Cache {

    private static function dir(): string {
        $d = dirname(__DIR__) . '/cache/data';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    private static function path(string $key): string {
        return self::dir() . '/' . hash('sha256', $key) . '.json';
    }

    public static function get(string $key, $default = null) {
        $f = self::path($key);
        if (!is_file($f)) return $default;

        $data = @file_get_contents($f);
        if ($data === false) return $default;

        $payload = json_decode($data, true);
        if (!is_array($payload) || !isset($payload['expires'])) return $default;

        if ($payload['expires'] > 0 && $payload['expires'] < time()) {
            @unlink($f);
            return $default;
        }
        return $payload['value'] ?? $default;
    }

    public static function put(string $key, $value, int $ttlSec = 0): bool {
        $payload = [
            'expires' => $ttlSec > 0 ? time() + $ttlSec : 0,
            'value'   => $value,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) return false;
        return @file_put_contents(self::path($key), $json, LOCK_EX) !== false;
    }

    public static function remember(string $key, int $ttlSec, callable $fn) {
        $cached = self::get($key, '__MISS__');
        if ($cached !== '__MISS__') return $cached;

        $value = $fn();
        self::put($key, $value, $ttlSec);
        return $value;
    }

    public static function forget(string $key): bool {
        $f = self::path($key);
        return is_file($f) ? @unlink($f) : true;
    }

    public static function flush(): int {
        $count = 0;
        foreach (glob(self::dir() . '/*.{json,cache}', GLOB_BRACE) as $f) {
            if (@unlink($f)) $count++;
        }
        return $count;
    }

    public static function flushExpired(): int {
        $count = 0;
        foreach (glob(self::dir() . '/*.json') as $f) {
            $data = @file_get_contents($f);
            if ($data === false) continue;
            $payload = json_decode($data, true);
            if (is_array($payload) && ($payload['expires'] ?? 0) > 0 && $payload['expires'] < time()) {
                if (@unlink($f)) $count++;
            }
        }
        // Eski .cache fayllar bo'lsa hammasini o'chiramiz (xavfsizlik)
        foreach (glob(self::dir() . '/*.cache') as $f) {
            if (@unlink($f)) $count++;
        }
        return $count;
    }

    public static function size(): int {
        $bytes = 0;
        foreach (glob(self::dir() . '/*.{json,cache}', GLOB_BRACE) as $f) {
            $bytes += filesize($f);
        }
        return $bytes;
    }

    public static function count(): int {
        return count(glob(self::dir() . '/*.json') ?: []);
    }
}
