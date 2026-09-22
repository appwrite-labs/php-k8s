<?php

namespace RenokiCo\PhpK8s\Test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Override;
use Psr\Http\Message\RequestInterface;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * A cluster that answers every call from canned responses and keeps the
 * requests it was asked to send, so a test can assert what went on the wire.
 */
class RecordingCluster extends KubernetesCluster
{
    /** @var array<int, array{request: RequestInterface}> */
    public array $transactions = [];

    /** @var array<int, Response> */
    protected array $responses = [];

    /**
     * @param  array<int, array<string, mixed>>  $bodies
     */
    public function respondWith(array $bodies): static
    {
        foreach ($bodies as $body) {
            $this->responses[] = new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
        }

        return $this;
    }

    #[Override]
    public function getClient(): Client
    {
        $stack = HandlerStack::create(new MockHandler($this->responses));
        $stack->push(Middleware::history($this->transactions));

        return new Client(['handler' => $stack]);
    }

    public function requests(): array
    {
        return array_map(
            fn (array $transaction): RequestInterface => $transaction['request'],
            $this->transactions
        );
    }
}
