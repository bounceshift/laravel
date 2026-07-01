<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Facades;

use BounceShift\Client;
use BounceShift\ValidationResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ValidationResult validate(string $email)
 *
 * @see \BounceShift\Client
 */
final class BounceShift extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
