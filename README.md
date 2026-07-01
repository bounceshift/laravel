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

$result->status->value;   // 'valid', 'invalid', 'catch_all', ...
$result->confidence;      // 0-100
$result->isSafeToSend();  // true for 'valid' or 'catch_all'
$result->fromCache;       // bool
$result->creditsUsed;     // int
```

You can also resolve the client directly from the container:

```php
use BounceShift\Client;

$result = app(Client::class)->validate('user@example.com');
```

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

If the API is unreachable (network/API outage), the rule **fails open** and passes, so an outage never blocks your users.

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
