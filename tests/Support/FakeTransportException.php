<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * A PSR-18 transport failure used to simulate a network outage in tests.
 */
final class FakeTransportException extends RuntimeException implements ClientExceptionInterface {}
