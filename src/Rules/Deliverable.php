<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Rules;

use BounceShift\Client;
use BounceShift\Exceptions\BounceShiftException;
use BounceShift\ValidationResult;
use BounceShift\ValidationStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

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
     * Whether a rejection message should offer the API's typo correction.
     */
    private bool $suggestCorrections = true;

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
     * Stop offering the API's typo correction in the failure message.
     *
     * On by default: when BounceShift rejects grace@gmil.com and knows the
     * address was probably grace@gmail.com, telling the person is the whole
     * difference between a dead end and a fixable form error.
     */
    public function withoutSuggestions(): self
    {
        $this->suggestCorrections = false;

        return $this;
    }

    /**
     * Override the failure message.
     *
     * The message may contain a :suggestion placeholder, replaced with the
     * corrected address when the API offers one.
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
        } catch (BounceShiftException $e) {
            // Fail open: never block user input on an API/network outage — but log
            // it, so an out-of-credits or an outage doesn't silently disable
            // validation without anyone noticing.
            Log::warning('BounceShift validation failed open; the address was allowed through unverified.', [
                'attribute' => $attribute,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        if ($this->isRejected($result)) {
            $fail($this->failureMessage($result));
        }
    }

    /**
     * The message for a rejected address, offering the typo correction when the
     * API found one. :attribute is left for the validator to substitute.
     */
    private function failureMessage(ValidationResult $result): string
    {
        $suggestion = $this->suggestCorrections ? $result->didYouMean : null;

        if ($this->message !== null) {
            return str_replace(':suggestion', (string) $suggestion, $this->message);
        }

        if ($suggestion !== null) {
            return 'The :attribute is not a deliverable email address. Did you mean '.$suggestion.'?';
        }

        return 'The :attribute is not a deliverable email address.';
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
