<?php
/**
 * Simple file-based caching
 */

class Cache {
    private static $cache_dir = __DIR__ . '/../storage/cache/';

    public static function init() {
        if (!is_dir(self::$cache_dir)) {
            mkdir(self::$cache_dir, 0755, true);
        }
    }

    public static function get($key) {
        $file = self::$cache_dir . md5($key) . '.cache';
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] > time()) {
                return $data['value'];
            }
        }
        return null;
    }

    public static function set($key, $value, $ttl = 3600) {
        $file = self::$cache_dir . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    public static function delete($key) {
        $file = self::$cache_dir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function clear() {
        $files = glob(self::$cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
Cache::init();