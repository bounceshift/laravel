<?php

declare(strict_types=1);

namespace BounceShift\Laravel\Tests\Support;

use BounceShift\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds a real {@see Client} backed by an in-memory PSR-18 client so tests
 * never touch the network. The core Client is final, so it cannot be
 * subclassed — this wires a canned response through the public options instead.
 */
final class StubClientFactory
{
    /**
     * Build a client whose next validate() call resolves to a given status.
     *
     * @param  array<string, mixed>  $overrides  Response-body key overrides.
     */
    public static function returningStatus(string $status, array $overrides = []): Client
    {
        $body = array_merge([
            'email' => 'user@example.com',
            'status' => $status,
            'confidence' => 90,
            'mx_found' => true,
            'smtp_valid' => true,
            'is_disposable' => false,
            'is_catch_all' => false,
            'is_role_account' => false,
            'from_cache' => false,
            'credits_used' => 1,
            'result' => [],
        ], $overrides);

        return self::withResponse(new Response(
            200,
            ['Content-Type' => 'application/json'],
            (string) json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * Build a client whose next request throws a PSR-18 transport exception.
     */
    public static function throwing(\Psr\Http\Client\ClientExceptionInterface $exception): Client
    {
        return self::withHttpClient(new class($exception) implements ClientInterface
        {
            public function __construct(private \Throwable $exception) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        });
    }

    /**
     * Build a client that returns a single canned PSR-7 response.
     */
    public static function withResponse(ResponseInterface $response): Client
    {
        return self::withHttpClient(new class($response) implements ClientInterface
        {
            public function __construct(private ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        });
    }

    /**
     * Build a client wired to a specific PSR-18 client and Guzzle PSR-17 factories.
     */
    public static function withHttpClient(ClientInterface $httpClient): Client
    {
        $factory = new HttpFactory;

        return new Client('test-key', 'test-org', [
            'http_client' => $httpClient,
            'request_factory' => $factory,
            'stream_factory' => $factory,
            'retries' => 0,
        ]);
    }
}
