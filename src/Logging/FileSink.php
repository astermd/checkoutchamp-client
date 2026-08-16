<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Logging;

use AsterMD\CheckoutChampClient\Config;
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Built-in log destination: one file per calendar day, with retention.
 *
 * A base path of "/var/log/checkoutchamp.log" produces "/var/log/checkoutchamp-2026-08-16.log",
 * "/var/log/checkoutchamp-2026-08-17.log", and so on.
 *
 * Pruning only ever considers files matching this package's own dated pattern
 * for the given base path, and reads the date from the filename rather than
 * from the filesystem mtime — an appended-to or restored file keeps its true
 * age. It runs at most once per process, not once per request.
 *
 * Supply a sink closure instead of a base path to replace this class entirely;
 * the package then writes no files at all and retention becomes your concern.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class FileSink
{
    /**
     * Days of history kept when the caller does not say otherwise.
     */
    public const DEFAULT_RETENTION_DAYS = 7;

    /**
     * Base paths already pruned in this process, keyed by base path.
     *
     * @var array<string, bool>
     */
    private static $pruned = [];

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var int
     */
    private $retentionDays;

    /**
     * @var DateTimeZone
     */
    private $timezone;

    /**
     * @param string $basePath      Caller-supplied path; the date is inserted before the extension.
     * @param int    $retentionDays Days of history to keep. 0 keeps everything.
     * @param string $timezone      IANA timezone used for both the filename date and pruning.
     *
     * @throws CheckoutChampException When the base path is empty or the timezone is not recognised.
     */
    public function __construct(
        string $basePath,
        int $retentionDays = self::DEFAULT_RETENTION_DAYS,
        string $timezone = 'UTC'
    ) {
        if (trim($basePath) === '') {
            throw new CheckoutChampException(Config::get('Messages.debugFileRequired'));
        }

        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            throw new CheckoutChampException(Config::get('Messages.invalidTimezone'));
        }

        $this->basePath      = $basePath;
        $this->retentionDays = max(0, $retentionDays);
    }

    /**
     * Append one entry to today's file, pruning old files on first use.
     *
     * Never throws: a logging failure must not break the API call that
     * triggered it.
     *
     * @param string $entry
     *
     * @return void
     */
    public function __invoke(string $entry): void
    {
        try {
            $now  = new DateTimeImmutable('now', $this->timezone);
            $path = $this->pathForDate($now);

            $directory = dirname($path);
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);

            $this->pruneOnce($now);
        } catch (Throwable $e) {
            // Intentionally swallowed.
        }
    }

    /**
     * Resolve the dated filename for a given day.
     *
     * @param DateTimeImmutable $date
     *
     * @return string
     */
    public function pathForDate(DateTimeImmutable $date): string
    {
        return $this->directory()
            . DIRECTORY_SEPARATOR
            . $this->stem()
            . '-'
            . $date->format('Y-m-d')
            . $this->extensionSuffix();
    }

    /**
     * Delete dated files older than the retention window.
     *
     * Only files matching this sink's own "<stem>-YYYY-MM-DD<.ext>" pattern in
     * the base path's directory are considered.
     *
     * @param DateTimeImmutable|null $now
     *
     * @return int Number of files deleted.
     */
    public function prune(?DateTimeImmutable $now = null): int
    {
        if ($this->retentionDays === 0) {
            return 0;
        }

        $now       = $now ?? new DateTimeImmutable('now', $this->timezone);
        $directory = $this->directory();
        if (!is_dir($directory)) {
            return 0;
        }

        $cutoff  = $now->sub(new DateInterval('P' . $this->retentionDays . 'D'))->format('Y-m-d');
        $pattern = '/^'
            . preg_quote($this->stem(), '/')
            . '-(\d{4}-\d{2}-\d{2})'
            . preg_quote($this->extensionSuffix(), '/')
            . '$/';

        $entries = scandir($directory);
        if ($entries === false) {
            return 0;
        }

        $deleted = 0;
        foreach ($entries as $name) {
            $matches = [];
            if (preg_match($pattern, $name, $matches) !== 1) {
                continue;
            }

            // Y-m-d sorts correctly as a plain string comparison.
            if ($matches[1] >= $cutoff) {
                continue;
            }

            if (@unlink($directory . DIRECTORY_SEPARATOR . $name)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Forget which base paths have been pruned.
     *
     * @internal Exposed for the package's own test suite.
     *
     * @return void
     */
    public static function resetPruneState(): void
    {
        self::$pruned = [];
    }

    /**
     * @param DateTimeImmutable $now
     *
     * @return void
     */
    private function pruneOnce(DateTimeImmutable $now): void
    {
        if (isset(self::$pruned[$this->basePath])) {
            return;
        }

        self::$pruned[$this->basePath] = true;
        $this->prune($now);
    }

    /**
     * @return string
     */
    private function directory(): string
    {
        return rtrim(dirname($this->basePath), DIRECTORY_SEPARATOR);
    }

    /**
     * Filename without its extension.
     *
     * @return string
     */
    private function stem(): string
    {
        $file      = basename($this->basePath);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $file;
        }

        return substr($file, 0, -(strlen($extension) + 1));
    }

    /**
     * Extension including the leading dot, or an empty string.
     *
     * @return string
     */
    private function extensionSuffix(): string
    {
        $extension = pathinfo(basename($this->basePath), PATHINFO_EXTENSION);

        if ($extension === '') {
            return '';
        }

        return '.' . $extension;
    }
}
