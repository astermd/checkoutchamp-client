<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient;

/**
 * Loads the package's bundled configuration files on first use.
 *
 * Each file in config/ becomes a top-level key named after the file, so
 * config/Messages.php is read as "Messages.<key>".
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Config
{
    /**
     * @var bool
     */
    private static $loaded = false;

    /**
     * Read a configuration string by dotted key.
     *
     * @param string $configKey e.g. "Messages.invalidAuth"
     * @param string $default
     *
     * @return string
     */
    public static function get(string $configKey, string $default = ''): string
    {
        self::load();

        return Repository::get($configKey, $default);
    }

    /**
     * Load config/*.php into the repository once per process.
     *
     * @return void
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $configPath  = dirname(__DIR__) . '/config';
        $configFiles = glob($configPath . '/*.php');
        if ($configFiles === false) {
            return;
        }

        foreach ($configFiles as $file) {
            /** @var mixed $values */
            $values = require $file;
            if (is_array($values)) {
                Repository::set(basename($file, '.php'), $values);
            }
        }
    }

    /**
     * Force the next read to reload from disk.
     *
     * @internal Exposed for the package's own test suite.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$loaded = false;
        Repository::flush();
    }
}
