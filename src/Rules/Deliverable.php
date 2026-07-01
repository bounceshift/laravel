<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Rules;

use BounceShift\Client;
use BounceShift\Exceptions\BounceShiftException;
use BounceShift\ValidationResult;
use BounceShift\ValidationStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that an email address is deliverable via the BounceShift API.
 *
 * The default policy blocks only statuses that are clearly undeliverable or
 * unsafe and never blocks on uncertainty. {@see self::strict()} additionally
 * rejects "risky" and "unknown"; {@see self::minConfidence()} rejects results
 * below a confidence threshold. On any API/network failure the rule fails open
 * (passes) so an outage never blocks user input.
 *
 * Caution: strict() and minConfidence() can reject legitimate users. On
 * infrastructure where SMTP probing is throttled (common for Outlook/Hotmail
 * and Gmail), real, deliverable addresses frequently return "unknown" with low
 * confidence — a probe limitation, not a quality signal. Prefer the lenient
 * default for public signup forms.
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
     * Optional minimum confidence (0-100); results below it are rejected.
     */
    private ?int $minConfidence = null;

    /**
     * @param  string|null  $message  Optional custom failure message.
     */
    public function __construct(private ?string $message = null) {}

    /**
     * Also reject uncertain results ("risky" and "unknown").
     *
     * Warning: on throttled SMTP infrastructure this rejects many real users;
     * prefer the lenient default for public signup forms.
     */
    public function strict(bool $strict = true): self
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * Reject any result whose confidence score is below the threshold (0-100).
     *
     * Warning: throttled probes return low-confidence "unknown" for real
     * addresses, so a high threshold can reject legitimate users. Use deliberately.
     */
    public function minConfidence(int $confidence): self
    {
        $this->minConfidence = $confidence;

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

        if ($this->isRejected($result)) {
            $fail($this->message ?? 'The :attribute is not a deliverable email address.');
        }
    }

    /**
     * Whether the given result should fail validation under the current policy.
     */
    private function isRejected(ValidationResult $result): bool
    {
        if (in_array($result->status, self::ALWAYS_REJECTED, true)) {
            return true;
        }

        if ($this->strict && in_array($result->status, self::STRICT_REJECTED, true)) {
            return true;
        }

        return $this->minConfidence !== null && $result->confidence < $this->minConfidence;
    }

    /**
     * Resolve the BounceShift client from the container.
     */
    private function client(): Client
    {
        return app(Client::class);
    }
}
