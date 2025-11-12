<?php

namespace Snowsoft\LaravelModelCaching;

/**
 * Cache Key Sanitizer
 *
 * Cache key bileşenlerini güvenli hale getirmek için sanitization işlemleri yapar.
 */
class CacheKeySanitizer
{
    /**
     * Maximum cache key length
     */
    protected const MAX_KEY_LENGTH = 250;

    /**
     * Maximum component length before hashing
     */
    protected const MAX_COMPONENT_LENGTH = 200;

    /**
     * Sanitize a cache key component
     *
     * @param string $component
     * @return string
     */
    public static function sanitize(string $component): string
    {
        if (!is_string($component)) {
            $component = (string) $component;
        }

        // Null bytes'ı temizle
        $component = str_replace("\0", '', $component);

        // Özel karakterleri temizle veya encode et
        // Alphanumeric, dash, underscore, colon'a izin ver
        $component = preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $component);

        // Length limiti
        if (strlen($component) > self::MAX_COMPONENT_LENGTH) {
            $component = substr($component, 0, self::MAX_COMPONENT_LENGTH);
        }

        return $component;
    }

    /**
     * Sanitize and hash long cache key
     *
     * @param string $key
     * @return string
     */
    public static function sanitizeAndHash(string $key): string
    {
        // Önce sanitize et
        $key = self::sanitize($key);

        // Uzun key'leri hash'le
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            $prefix = substr($key, 0, self::MAX_COMPONENT_LENGTH);
            $hash = hash('sha256', $key);
            $key = $prefix . ':' . substr($hash, 0, 16);
        }

        return $key;
    }

    /**
     * Validate cache key format
     *
     * @param string $key
     * @return bool
     */
    public static function validate(string $key): bool
    {
        // Null bytes kontrolü
        if (strpos($key, "\0") !== false) {
            return false;
        }

        // Length kontrolü
        if (strlen($key) > self::MAX_KEY_LENGTH * 2) {
            return false;
        }

        return true;
    }

    /**
     * Create a safe cache key from components
     *
     * @param array $components
     * @return string
     */
    public static function createKey(array $components): string
    {
        $sanitized = array_map([self::class, 'sanitize'], $components);
        $key = implode(':', $sanitized);

        return self::sanitizeAndHash($key);
    }
}
