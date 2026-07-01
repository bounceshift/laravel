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

### Strict mode

`->strict()` additionally rejects the uncertain `risky` and `unknown` statuses:

```php
$request->validate([
    'email' => ['required', 'email', (new Deliverable)->strict()],
]);
```

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
