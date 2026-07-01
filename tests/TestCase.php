<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Tests;

use BounceShift\Laravel\BounceShiftServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package service provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BounceShiftServiceProvider::class];
    }

    /**
     * Register the package facade alias.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['BounceShift' => \BounceShift\Laravel\Facades\BounceShift::class];
    }
}
