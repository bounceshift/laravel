<?php

declare(strict_types=1);

use BounceShift\Client;
use BounceShift\Laravel\Facades\BounceShift;
use BounceShift\Laravel\Tests\Support\StubClientFactory;
use BounceShift\Recommendation;

/**
 * These cover the adapter flowing the core ValidationResult value object through
 * unchanged — the newer sub_status / recommendation / quality_score / explanation
 * fields must surface via the facade exactly as the API sends them, and must
 * tolerate null, absent, and unrecognized recommendation values without throwing.
 *
 * @param  array<string, mixed>  $overrides
 */
function validateThroughFacade(array $overrides): \BounceShift\ValidationResult
{
    app()->instance(Client::class, StubClientFactory::returningStatus('valid', $overrides));

    return BounceShift::validate('user@example.com');
}

it('surfaces the four new response fields through the facade', function (): void {
    $result = validateThroughFacade([
        'confidence' => 90,
        'sub_status' => 'smtp_verified',
        'recommendation' => 'deliverable',
        'quality_score' => 82,
        'explanation' => 'The mailbox exists and accepts mail.',
    ]);

    expect($result->subStatus)->toBe('smtp_verified')
        ->and($result->recommendation)->toBe(Recommendation::Deliverable)
        ->and($result->recommendationValue)->toBe('deliverable')
        ->and($result->qualityScore)->toBe(82)
        ->and($result->explanation)->toBe('The mailbox exists and accepts mail.')
        ->and($result->isSendable())->toBeTrue();
});

it('models quality_score separately from confidence rather than aliasing it', function (): void {
    $result = validateThroughFacade([
        'confidence' => 90,
        'quality_score' => 41,
    ]);

    expect($result->qualityScore)->toBe(41)
        ->and($result->confidence)->toBe(90)
        ->and($result->qualityScore)->not->toBe($result->confidence);
});

it('reports isSendable() true for a sendable recommendation', function (string $recommendation): void {
    $result = validateThroughFacade(['recommendation' => $recommendation]);

    expect($result->recommendation?->isSendable())->toBeTrue()
        ->and($result->isSendable())->toBeTrue();
})->with(['deliverable', 'send_with_caution']);

it('reports isSendable() false for a non-sendable recommendation', function (string $recommendation): void {
    $result = validateThroughFacade(['recommendation' => $recommendation]);

    expect($result->recommendation?->isSendable())->toBeFalse()
        ->and($result->isSendable())->toBeFalse();
})->with(['risky', 'undeliverable', 'unknown']);

it('does not throw when the recommendation is null and is not sendable', function (): void {
    $result = validateThroughFacade(['recommendation' => null]);

    expect($result->recommendation)->toBeNull()
        ->and($result->recommendationValue)->toBeNull()
        ->and($result->isSendable())->toBeFalse();
});

it('does not throw when the recommendation is absent and defaults safely', function (): void {
    // returningStatus() omits the new keys by default, exercising the absent path.
    $result = validateThroughFacade([]);

    expect($result->recommendation)->toBeNull()
        ->and($result->recommendationValue)->toBeNull()
        ->and($result->subStatus)->toBeNull()
        ->and($result->qualityScore)->toBeNull()
        ->and($result->explanation)->toBeNull()
        ->and($result->isSendable())->toBeFalse();
});

it('does not throw on an unknown recommendation string and treats it as not sendable', function (): void {
    $result = validateThroughFacade(['recommendation' => 'quantum_maybe']);

    expect($result->recommendation)->toBeNull()
        ->and($result->recommendationValue)->toBe('quantum_maybe')
        ->and($result->isSendable())->toBeFalse();
});

it('keeps the status-based isSafeToSend() working alongside the recommendation surface', function (): void {
    // Backwards compatibility: isSafeToSend() stays status-driven and independent of recommendation.
    $result = validateThroughFacade(['status' => 'valid', 'recommendation' => 'undeliverable']);

    expect($result->isSafeToSend())->toBeTrue()
        ->and($result->isSendable())->toBeFalse();
});
