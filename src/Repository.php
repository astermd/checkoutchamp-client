<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * In-memory configuration store with dotted-key lookup.
 *
 * Deliberately dependency-free: the package needs somewhere to keep a handful
 * of message strings, which is not a reason to drag a framework into every
 * consumer's dependency tree.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Repository
{
    /**
     * @var array<string, mixed>
     */
    private static $config = [];

    /**
     * Read the string stored at a dotted key.
     *
     * @param string $configKey e.g. "Messages.methodNotFound"
     * @param string $default   Returned when the key is absent or does not hold a string.
     *
     * @return string
     */
    public static function get(string $configKey, string $default = ''): string
    {
        if ($configKey === '') {
            return $default;
        }

        /** @var mixed $value */
        $value = self::$config;
        foreach (explode('.', $configKey) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            /** @var mixed $value */
            $value = $value[$segment];
        }

        return is_string($value) ? $value : $default;
    }

    /**
     * Store a value under a top-level key.
     *
     * @param string $configKey
     * @param mixed  $configVal
     *
     * @return bool
     */
    public static function set(string $configKey, $configVal): bool
    {
        if ($configKey === '') {
            return false;
        }

        self::$config[$configKey] = $configVal;

        return true;
    }

    /**
     * Discard everything currently stored.
     *
     * @internal Exposed for the package's own test suite.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$config = [];
    }
}
