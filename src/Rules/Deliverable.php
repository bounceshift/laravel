<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Rules;

use BounceShift\Client;
use BounceShift\Exceptions\BounceShiftException;
use BounceShift\ValidationStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that an email address is deliverable via the BounceShift API.
 *
 * The default policy blocks only statuses that are clearly undeliverable or
 * unsafe and never blocks on uncertainty. Enabling {@see self::strict()} also
 * rejects "risky" and "unknown" results. On any API/network failure the rule
 * fails open (passes) so an outage never blocks user input.
 */
final class Deliverable implements ValidationRule
{
    /**
     * Statuses that always fail validation, regardless of strictness.
     *
     * @var list<ValidationStatus>
     */
    private const ALWAYS_REJECTED = [
        ValidationStatus::Invalid,
        ValidationStatus::Disposable,
        ValidationStatus::DoNotMail,
        ValidationStatus::Abuse,
        ValidationStatus::SpamTrap,
    ];

    /**
     * Additional statuses rejected only in strict mode.
     *
     * @var list<ValidationStatus>
     */
    private const STRICT_REJECTED = [
        ValidationStatus::Risky,
        ValidationStatus::Unknown,
    ];

    /**
     * Whether uncertain results ("risky", "unknown") should also be rejected.
     */
    private bool $strict = false;

    /**
     * @param  string|null  $message  Optional custom failure message.
     */
    public function __construct(private ?string $message = null) {}

    /**
     * Also reject uncertain results ("risky" and "unknown").
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * Override the failure message.
     */
    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $result = $this->client()->validate($value);
        } catch (BounceShiftException) {
            // Fail open: never block user input on an API/network outage.
            return;
        }

        if ($this->isRejected($result->status)) {
            $fail($this->message ?? 'The :attribute is not a deliverable email address.');
        }
    }

    /**
     * Whether the given status should fail validation under the current policy.
     */
    private function isRejected(ValidationStatus $status): bool
    {
        if (in_array($status, self::ALWAYS_REJECTED, true)) {
            return true;
        }

        return $this->strict && in_array($status, self::STRICT_REJECTED, true);
    }

    /**
     * Resolve the BounceShift client from the container.
     */
    private function client(): Client
    {
        return app(Client::class);
    }
}
