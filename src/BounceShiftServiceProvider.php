<?php

declare(strict_types=1);

namespace BounceShift\Laravel;

use BounceShift\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the BounceShift client and publishes package configuration.
 */
final class BounceShiftServiceProvider extends ServiceProvider
{
    /**
     * The path to the package configuration file.
     */
    private const CONFIG_PATH = __DIR__.'/../config/bounceshift.php';

    /**
     * Register the client singleton and merge default configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'bounceshift');

        $this->app->singleton(Client::class, static function (Container $app): Client {
            /** @var array{key: ?string, organization_id: ?string, base_url: string, timeout: int, connect_timeout: int, retries: int} $config */
            $config = $app->make('config')->get('bounceshift');

            return new Client(
                (string) ($config['key'] ?? ''),
                (string) ($config['organization_id'] ?? ''),
                [
                    'base_url' => $config['base_url'] ?? Client::DEFAULT_BASE_URL,
                    'timeout' => (int) ($config['timeout'] ?? 10),
                    'connect_timeout' => (int) ($config['connect_timeout'] ?? 5),
                    'retries' => (int) ($config['retries'] ?? 2),
                ],
            );
        });
    }

    /**
     * Bootstrap publishable resources.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->app->configPath('bounceshift.php'),
            ], 'bounceshift-config');
        }
    }
}
