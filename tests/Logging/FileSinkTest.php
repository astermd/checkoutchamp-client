<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests\Logging;

use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;
use AsterMD\CheckoutChampClient\Logging\FileSink;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\CheckoutChampClient\Logging\FileSink::class)]
final class FileSinkTest extends TestCase
{
    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        FileSink::resetPruneState();

        $this->directory = sys_get_temp_dir() . '/checkoutchamp-client-tests-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->directory);

        FileSink::resetPruneState();
        parent::tearDown();
    }

    /**
     * @param string $path
     *
     * @return void
     */
    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = glob($path . '/*');
        foreach (($entries === false ? [] : $entries) as $entry) {
            if (is_dir($entry)) {
                $this->removeTree($entry);
                continue;
            }
            unlink($entry);
        }

        rmdir($path);
    }

    public function testTheDateIsInsertedBeforeTheExtension(): void
    {
        $sink = new FileSink($this->directory . '/checkoutchamp.log');
        $date = new DateTimeImmutable('2026-08-16', new DateTimeZone('UTC'));

        self::assertSame($this->directory . '/checkoutchamp-2026-08-16.log', $sink->pathForDate($date));
    }

    public function testABasePathWithoutAnExtensionStillGetsADate(): void
    {
        $sink = new FileSink($this->directory . '/checkoutchamp');
        $date = new DateTimeImmutable('2026-08-16', new DateTimeZone('UTC'));

        self::assertSame($this->directory . '/checkoutchamp-2026-08-16', $sink->pathForDate($date));
    }

    public function testEntriesAreAppendedToTodaysFile(): void
    {
        $sink = new FileSink($this->directory . '/checkoutchamp.log');
        $sink('first entry' . PHP_EOL);
        $sink('second entry' . PHP_EOL);

        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $path  = $this->directory . '/checkoutchamp-' . $today . '.log';

        self::assertFileExists($path);
        self::assertSame('first entry' . PHP_EOL . 'second entry' . PHP_EOL, file_get_contents($path));
        self::assertCount(1, (array) glob($this->directory . '/*.log'));
    }

    public function testAMissingDirectoryIsCreated(): void
    {
        $nested = $this->directory . '/nested';
        $sink   = new FileSink($nested . '/checkoutchamp.log');
        $sink('entry');

        self::assertDirectoryExists($nested);
        self::assertCount(1, (array) glob($nested . '/*.log'));
    }

    public function testPruningRemovesOnlyExpiredDatedFiles(): void
    {
        $today  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $recent = (new DateTimeImmutable('-2 days', new DateTimeZone('UTC')))->format('Y-m-d');

        $this->write('checkoutchamp-2020-01-01.log');
        $this->write('checkoutchamp-' . $recent . '.log');
        $this->write('checkoutchamp-' . $today . '.log');
        $this->write('checkoutchamp-not-a-date.log');
        $this->write('unrelated.log');
        $this->write('other-2020-01-01.log');

        $sink = new FileSink($this->directory . '/checkoutchamp.log', 7);

        self::assertSame(1, $sink->prune());
        self::assertFileDoesNotExist($this->directory . '/checkoutchamp-2020-01-01.log');
        self::assertFileExists($this->directory . '/checkoutchamp-' . $recent . '.log');
        self::assertFileExists($this->directory . '/checkoutchamp-' . $today . '.log');
        self::assertFileExists($this->directory . '/checkoutchamp-not-a-date.log');
        self::assertFileExists($this->directory . '/unrelated.log');
        self::assertFileExists($this->directory . '/other-2020-01-01.log');
    }

    public function testARetentionOfZeroKeepsEverything(): void
    {
        $this->write('checkoutchamp-2020-01-01.log');

        $sink = new FileSink($this->directory . '/checkoutchamp.log', 0);

        self::assertSame(0, $sink->prune());
        self::assertFileExists($this->directory . '/checkoutchamp-2020-01-01.log');
    }

    public function testTheAgeComesFromTheFilenameNotTheModificationTime(): void
    {
        $path = $this->write('checkoutchamp-2020-01-01.log');
        touch($path, time());

        $sink = new FileSink($this->directory . '/checkoutchamp.log', 7);

        self::assertSame(1, $sink->prune());
        self::assertFileDoesNotExist($path);
    }

    public function testPruningRunsOncePerProcess(): void
    {
        $sink = new FileSink($this->directory . '/checkoutchamp.log', 7);

        $this->write('checkoutchamp-2020-01-01.log');
        $sink('first');
        self::assertFileDoesNotExist($this->directory . '/checkoutchamp-2020-01-01.log');

        $this->write('checkoutchamp-2020-02-02.log');
        $sink('second');
        self::assertFileExists($this->directory . '/checkoutchamp-2020-02-02.log');
    }

    public function testAnEmptyBasePathIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);

        new FileSink('   ');
    }

    public function testAnUnknownTimezoneIsRejected(): void
    {
        $this->expectException(CheckoutChampException::class);
        $this->expectExceptionMessage('IANA');

        new FileSink($this->directory . '/checkoutchamp.log', 7, 'Nowhere/Nothing');
    }

    public function testANegativeRetentionIsTreatedAsKeepEverything(): void
    {
        $this->write('checkoutchamp-2020-01-01.log');

        $sink = new FileSink($this->directory . '/checkoutchamp.log', -5);

        self::assertSame(0, $sink->prune());
        self::assertFileExists($this->directory . '/checkoutchamp-2020-01-01.log');
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function write(string $name): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, 'x');

        return $path;
    }
}
