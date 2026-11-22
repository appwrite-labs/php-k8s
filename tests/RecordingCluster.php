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

    protected ?MockHandler $handler = null;

    protected ?Client $client = null;

    /**
     * @param  array<int, array<string, mixed>>  $bodies
     */
    public function respondWith(array $bodies): static
    {
        $this->handler ??= new MockHandler;

        foreach ($bodies as $body) {
            $this->handler->append(
                new Response(200, ['Content-Type' => 'application/json'], json_encode($body))
            );
        }

        return $this;
    }

    /**
     * One client, and therefore one response queue, for the life of the
     * cluster: a fresh handler per call would replay the first response to
     * every request and no multi-request flow could be modelled.
     */
    #[Override]
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->handler ??= new MockHandler;

            $stack = HandlerStack::create($this->handler);
            $stack->push(Middleware::history($this->transactions));

            $this->client = new Client(['handler' => $stack]);
        }

        return $this->client;
    }

    /**
     * @return array<int, RequestInterface>
     */
    public function requests(): array
    {
        return array_map(
            fn (array $transaction): RequestInterface => $transaction['request'],
            $this->transactions
        );
    }
}
