<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Tests;

use AsterMD\CheckoutChampClient\Config;
use AsterMD\CheckoutChampClient\Repository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\CheckoutChampClient\Config::class)]
#[CoversClass(\AsterMD\CheckoutChampClient\Repository::class)]
final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        parent::tearDown();
    }

    public function testItReadsBundledMessages(): void
    {
        Config::reset();

        self::assertSame('No such method found', Config::get('Messages.methodNotFound'));
        self::assertSame('A login ID and password are required', Config::get('Messages.invalidApiAuth'));
        self::assertSame('No API method has been invoked yet', Config::get('Messages.apiInvokedFailure'));
    }

    public function testAnUnknownKeyReturnsTheDefault(): void
    {
        self::assertSame('', Config::get('Messages.nothingHere'));
        self::assertSame('fallback', Config::get('Messages.nothingHere', 'fallback'));
        self::assertSame('fallback', Config::get('NoSuchFile.key', 'fallback'));
    }

    public function testAnEmptyKeyReturnsTheDefault(): void
    {
        self::assertSame('fallback', Config::get('', 'fallback'));
    }

    public function testLoadingIsIdempotent(): void
    {
        Config::load();
        Config::load();

        self::assertSame('No such method found', Config::get('Messages.methodNotFound'));
    }

    public function testTheRepositoryStoresAndReadsNestedValues(): void
    {
        Repository::flush();

        self::assertTrue(Repository::set('Sample', ['nested' => ['leaf' => 'value']]));
        self::assertSame('value', Repository::get('Sample.nested.leaf'));
        self::assertSame('', Repository::get('Sample.nested'));
        self::assertSame('none', Repository::get('Sample.missing', 'none'));
    }

    public function testTheRepositoryRejectsAnEmptyKey(): void
    {
        self::assertFalse(Repository::set('', 'anything'));
        self::assertSame('d', Repository::get('', 'd'));
    }

    public function testFlushDiscardsEverything(): void
    {
        Repository::set('Sample', ['leaf' => 'value']);
        Repository::flush();

        self::assertSame('', Repository::get('Sample.leaf'));
    }
}
