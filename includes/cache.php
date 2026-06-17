<?php
/**
 * Oddiy fayl-bazli cache layer
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
        return self::dir() . '/' . md5($key) . '.cache';
    }

    public static function get(string $key, $default = null) {
        $f = self::path($key);
        if (!is_file($f)) return $default;

        $data = @file_get_contents($f);
        if ($data === false) return $default;

        $payload = @unserialize($data);
        if (!is_array($payload) || !isset($payload['expires'], $payload['value'])) return $default;

        if ($payload['expires'] > 0 && $payload['expires'] < time()) {
            @unlink($f);
            return $default;
        }
        return $payload['value'];
    }

    public static function put(string $key, $value, int $ttlSec = 0): bool {
        $payload = [
            'expires' => $ttlSec > 0 ? time() + $ttlSec : 0,
            'value'   => $value,
        ];
        return @file_put_contents(self::path($key), serialize($payload)) !== false;
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
        foreach (glob(self::dir() . '/*.cache') as $f) {
            if (@unlink($f)) $count++;
        }
        return $count;
    }

    public static function flushExpired(): int {
        $count = 0;
        foreach (glob(self::dir() . '/*.cache') as $f) {
            $data = @file_get_contents($f);
            if ($data === false) continue;
            $payload = @unserialize($data);
            if (is_array($payload) && ($payload['expires'] ?? 0) > 0 && $payload['expires'] < time()) {
                if (@unlink($f)) $count++;
            }
        }
        return $count;
    }

    public static function size(): int {
        $bytes = 0;
        foreach (glob(self::dir() . '/*.cache') as $f) {
            $bytes += filesize($f);
        }
        return $bytes;
    }

    public static function count(): int {
        return count(glob(self::dir() . '/*.cache') ?: []);
    }
}
