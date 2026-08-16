<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Exception;

use Exception;

/**
 * Base exception for every error raised by this package.
 *
 * Extends \Exception so existing `catch (\Exception $e)` blocks keep working.
 *
 * @package AsterMD\CheckoutChampClient
 */
class CheckoutChampException extends Exception
{
}
