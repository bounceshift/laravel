<?php

declare(strict_types=1);

use BounceShift\Client;
use BounceShift\Laravel\Rules\Deliverable;
use BounceShift\Laravel\Tests\Support\FakeTransportException;
use BounceShift\Laravel\Tests\Support\StubClientFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Run the Deliverable rule against a single value, returning the failure
 * messages produced (empty when the value passes).
 *
 * @return array<int, string>
 */
function runRule(Deliverable $rule, string $value = 'user@example.com'): array
{
    $validator = Validator::make(
        ['email' => $value],
        ['email' => [$rule]],
    );

    return $validator->errors()->get('email');
}

it('passes a valid address', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('valid'));

    expect(runRule(new Deliverable))->toBe([]);
});

it('fails an invalid address', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('invalid'));

    expect(runRule(new Deliverable))->not->toBe([]);
});

it('fails a disposable address', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('disposable'));

    expect(runRule(new Deliverable))->not->toBe([]);
});

it('fails spamtrap, abuse and do_not_mail addresses', function (string $status): void {
    app()->instance(Client::class, StubClientFactory::returningStatus($status));

    expect(runRule(new Deliverable))->not->toBe([]);
})->with(['spamtrap', 'abuse', 'do_not_mail']);

it('passes an unknown address in lenient mode', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('unknown'));

    expect(runRule(new Deliverable))->toBe([]);
});

it('passes risky and catch_all in lenient mode', function (string $status): void {
    app()->instance(Client::class, StubClientFactory::returningStatus($status));

    expect(runRule(new Deliverable))->toBe([]);
})->with(['risky', 'catch_all']);

it('fails an unknown address under strict mode', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('unknown'));

    expect(runRule((new Deliverable)->strict()))->not->toBe([]);
});

it('fails a risky address under strict mode', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('risky'));

    expect(runRule((new Deliverable)->strict()))->not->toBe([]);
});

it('passes a high-confidence result above the minConfidence floor', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('valid', ['confidence' => 95]));

    expect(runRule((new Deliverable)->minConfidence(70)))->toBe([]);
});

it('fails a low-confidence result below the minConfidence floor', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('valid', ['confidence' => 40]));

    expect(runRule((new Deliverable)->minConfidence(70)))->not->toBe([]);
});

it('passes a zero-confidence unknown in the lenient default', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('unknown', ['confidence' => 0]));

    expect(runRule(new Deliverable))->toBe([]);
});

it('rejects a zero-confidence unknown only once a confidence floor is set', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('unknown', ['confidence' => 0]));

    expect(runRule((new Deliverable)->minConfidence(50)))->not->toBe([]);
});

it('fails open when the client throws', function (): void {
    app()->instance(Client::class, StubClientFactory::throwing(
        new FakeTransportException('network down'),
    ));

    expect(runRule(new Deliverable))->toBe([]);
});

it('logs a warning when it fails open, so an outage is never silent', function (): void {
    Log::spy();

    app()->instance(Client::class, StubClientFactory::throwing(
        new FakeTransportException('network down'),
    ));

    expect(runRule(new Deliverable))->toBe([]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'failed open')
            && $context['attribute'] === 'email');
});

it('uses a custom failure message', function (): void {
    app()->instance(Client::class, StubClientFactory::returningStatus('invalid'));

    $messages = runRule((new Deliverable)->message('Bad address.'));

    expect($messages)->toContain('Bad address.');
});
