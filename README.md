# BounceShift for Laravel

Laravel adapter for the [BounceShift](https://bounceshift.com) email-validation SDK. It wires the framework-agnostic [`bounceshift/bounceshift-php`](https://github.com/bounceshift/bounceshift-php) client into the container, adds a `BounceShift` facade, and ships a `Deliverable` validation rule for forms and requests.

- API reference: <https://bounceshift.com/docs/api>

## Requirements

- PHP `^8.2`
- Laravel `^11.0 | ^12.0 | ^13.0`

## Installation

```bash
composer require bounceshift/laravel
```

The service provider and `BounceShift` facade are auto-discovered.

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=bounceshift-config
```

## Configuration

Set your credentials in `.env`:

```dotenv
BOUNCESHIFT_API_KEY=your-api-key
BOUNCESHIFT_ORGANIZATION_ID=your-organization-id

# Optional
BOUNCESHIFT_BASE_URL=https://api.bounceshift.com/v1
BOUNCESHIFT_TIMEOUT=10
BOUNCESHIFT_RETRIES=2
```

Both `BOUNCESHIFT_API_KEY` and `BOUNCESHIFT_ORGANIZATION_ID` are required.

The published `config/bounceshift.php`:

```php
return [
    'key' => env('BOUNCESHIFT_API_KEY'),
    'organization_id' => env('BOUNCESHIFT_ORGANIZATION_ID'),
    'base_url' => env('BOUNCESHIFT_BASE_URL', \BounceShift\Client::DEFAULT_BASE_URL),
    'timeout' => (int) env('BOUNCESHIFT_TIMEOUT', 10),
    'retries' => (int) env('BOUNCESHIFT_RETRIES', 2),
];
```

## Quickstart

### Facade

```php
use BounceShift\Laravel\Facades\BounceShift;

$result = BounceShift::validate('user@example.com');

$result->status->value;         // 'valid', 'invalid', 'catch_all', ...
$result->confidence;            // 0-100
$result->isSafeToSend();        // true for status 'valid' or 'catch_all'
$result->fromCache;             // bool
$result->creditsUsed;           // int

// Actionable send verdict from the API (distinct from status)
$result->recommendation?->value; // 'deliverable' | 'send_with_caution' | 'risky' | 'undeliverable' | 'unknown', or null
$result->recommendationValue;    // raw recommendation string as sent (preserved even if unrecognized), or null
$result->isSendable();           // true only for 'deliverable' or 'send_with_caution'
$result->subStatus;              // granular reason string, e.g. 'smtp_verified', or null
$result->qualityScore;           // 0-100, its own field (may diverge from confidence), or null
$result->explanation;            // plain-English sentence describing the verdict, or null
```

`recommendation` is a `BounceShift\Recommendation` enum; an absent, null, or unrecognized value surfaces as `null` (with the raw string still available on `recommendationValue`) and `isSendable()` returns `false`. `isSafeToSend()` is unchanged and remains status-based.

You can also resolve the client directly from the container:

```php
use BounceShift\Client;

$result = app(Client::class)->validate('user@example.com');
```

### Fail open — never block your users

`validate()` throws typed exceptions on failure. On a hot path such as
validate-on-signup, use `validateSafe()` instead — it **never throws**. If your
account runs out of credits, or the API is down or timing out, it returns a
degraded result rather than an exception, so you can let the address through:

```php
$result = BounceShift::validateSafe('user@example.com');

if ($result->isDegraded()) {
    // Out of credits / outage / timeout — the address is unverified.
    // Let it through, and alert your team.
} elseif (! $result->isSafeToSend()) {
    // A real verdict came back and it's not safe — reject.
}
```

A degraded result has status `unknown`, `creditsUsed = 0`, and
`isDegraded() === true`, so you can always tell "we couldn't check" apart from a
genuine `unknown` verdict. The `timeout` / `connect_timeout` config bounds how
long a stalled API can hold your request before `validateSafe()` gives up.

### The `Deliverable` validation rule

Use `Deliverable` to block undeliverable addresses in form requests and validators:

```php
use BounceShift\Laravel\Rules\Deliverable;

$request->validate([
    'email' => ['required', 'email', new Deliverable],
]);
```

**Default (lenient) policy** — it never blocks on uncertainty:

| Status                                              | Result   |
| --------------------------------------------------- | -------- |
| `invalid`, `disposable`, `do_not_mail`, `abuse`, `spamtrap` | ❌ fails  |
| `valid`, `catch_all`, `unknown`, `risky`            | ✅ passes |

If the API is unreachable (network/API outage) or your account is out of credits, the rule **fails open** and passes, so it never blocks your users. It logs a `warning` each time it does, so an outage or exhausted balance is never silent.

> [!WARNING]
> **`strict()` and `minConfidence()` can reject real users.** On infrastructure where the SMTP probe is throttled — common for **Outlook/Hotmail and Gmail** — legitimate, deliverable addresses often come back as `unknown` with low confidence. That is a probe limitation, *not* a quality signal. For public signup forms, prefer the **lenient default**, which never blocks on uncertainty. Only reach for strict mode or a confidence floor when you know your `unknown` rate is low and you would rather lose a few real addresses than accept any risk.

### Strict mode

`->strict()` additionally rejects the uncertain `risky` and `unknown` statuses (see the warning above):

```php
$request->validate([
    'email' => ['required', 'email', (new Deliverable)->strict()],
]);
```

### Confidence threshold

`->minConfidence(int)` rejects any result scoring below the given threshold (0–100), on top of the always-rejected statuses. It is a graded alternative to `strict()`:

```php
$request->validate([
    'email' => ['required', 'email', (new Deliverable)->minConfidence(70)],
]);
```

Because throttled probes return low-confidence `unknown` for real addresses, treat this exactly like `strict()` — see the warning above. It can be combined with `strict()`.

### Typo suggestions

When BounceShift rejects an address whose domain looks like a misspelling, the
failure message offers the correction:

```
The email is not a deliverable email address. Did you mean grace@gmail.com?
```

This is on by default, because a rejection someone can act on is worth far more
than one they cannot. Turn it off with `->withoutSuggestions()`, or place it
yourself in a custom message with the `:suggestion` placeholder:

```php
(new Deliverable)->message('That address looks wrong. Did you mean :suggestion?');
```

The correction is also available directly on the result, whatever the verdict:

```php
$result = BounceShift::validate('grace@gmil.com');

$result->didYouMean;      // 'grace@gmail.com'
$result->hasSuggestion(); // true
```

It is advisory — the API validates the address you sent, never the suggestion —
so show it to the person who typed it rather than substituting it. Note it is
populated on any status, including `valid`, because misspellings like
`gmil.com` are registered and accept mail: they never bounce, so this is the
only signal you get.

### Custom message

```php
(new Deliverable)->message('Please use a real, reachable email address.');

// or in strict mode
(new Deliverable)->strict()->message('That email cannot receive mail.');
```

## Local development

This package consumes the core SDK from a sibling directory via a Composer `path` repository:

```
your-workspace/
├── bounceshift-php/       # bounceshift/bounceshift-php (core)
└── bounceshift-laravel/   # this package
```

```bash
composer install
composer test
```

## Testing

```bash
composer test
```

Tests run against `orchestra/testbench` with a stubbed client — no network access is required.

## License

MIT. See [LICENSE](LICENSE).
