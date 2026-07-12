<?php

declare(strict_types=1);

use BounceShift\Client;

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your BounceShift API key, sent as a Bearer token on every request.
    |
    */

    'key' => env('BOUNCESHIFT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Organization ID
    |--------------------------------------------------------------------------
    |
    | Your BounceShift organization identifier, sent in the X-Organization-ID
    | header. Both the key and the organization ID are required.
    |
    */

    'organization_id' => env('BOUNCESHIFT_ORGANIZATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the BounceShift API. Override this to point at a
    | staging environment or a self-hosted instance.
    |
    */

    'base_url' => env('BOUNCESHIFT_BASE_URL', Client::DEFAULT_BASE_URL),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for an API response. This bounds how
    | long a stalled API can hold a request thread before the SDK gives up.
    |
    */

    'timeout' => (int) env('BOUNCESHIFT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Connect Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait while establishing the connection,
    | so an unreachable host fails fast instead of hanging.
    |
    */

    'connect_timeout' => (int) env('BOUNCESHIFT_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | The number of times to retry a request on a 429 or 5xx response before
    | giving up. A short backoff is applied between attempts.
    |
    */

    'retries' => (int) env('BOUNCESHIFT_RETRIES', 2),

];
