<?php

declare(strict_types=1);

use BounceShift\Client;
use BounceShift\Laravel\Facades\BounceShift;
use BounceShift\Laravel\Tests\Support\StubClientFactory;
use BounceShift\ValidationResult;

it('registers the client as a singleton', function (): void {
    config()->set('bounceshift.key', 'k');
    config()->set('bounceshift.organization_id', 'o');

    $first = app(Client::class);
    $second = app(Client::class);

    expect($first)->toBeInstanceOf(Client::class)
        ->and($first)->toBe($second);
});

it('resolves the BounceShift facade to the client', function (): void {
    $result = StubClientFactory::returningStatus('valid')->validate('user@example.com');

    app()->instance(Client::class, StubClientFactory::returningStatus('valid'));

    expect(BounceShift::getFacadeRoot())->toBeInstanceOf(Client::class)
        ->and(BounceShift::validate('user@example.com'))->toBeInstanceOf(ValidationResult::class)
        ->and(BounceShift::validate('user@example.com')->status->value)->toBe('valid')
        ->and($result)->toBeInstanceOf(ValidationResult::class);
});

it('publishes the config file under the bounceshift-config tag', function (): void {
    $this->artisan('vendor:publish', [
        '--tag' => 'bounceshift-config',
        '--force' => true,
    ])->assertSuccessful();

    expect(file_exists(config_path('bounceshift.php')))->toBeTrue();

    @unlink(config_path('bounceshift.php'));
});

it('merges default config values', function (): void {
    expect(config('bounceshift.base_url'))->toBe(Client::DEFAULT_BASE_URL)
        ->and(config('bounceshift.timeout'))->toBe(10)
        ->and(config('bounceshift.retries'))->toBe(2);
});
