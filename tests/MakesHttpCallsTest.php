<?php

namespace RenokiCo\PhpK8s\Test;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class MakesHttpCallsTest extends TestCase
{
    public function test_plain_text_error_keeps_http_status_and_chains_guzzle_exception(): void
    {
        $exception = $this->failedCall(
            new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], "404 page not found\n")
        );

        $this->assertSame(404, $exception->getCode());
        $this->assertNull($exception->getPayload());
        $this->assertInstanceOf(ClientException::class, $exception->getPrevious());
    }

    public function test_status_body_code_wins_over_http_status(): void
    {
        $exception = $this->failedCall(
            new Response(400, ['Content-Type' => 'application/json'], '{"kind":"Status","apiVersion":"v1","status":"Failure","reason":"Conflict","code":409}')
        );

        $this->assertSame(409, $exception->getCode());
        $this->assertSame(
            ['kind' => 'Status', 'apiVersion' => 'v1', 'status' => 'Failure', 'reason' => 'Conflict', 'code' => 409],
            $exception->getPayload()
        );
    }

    public function test_zero_status_body_code_falls_back_to_http_status(): void
    {
        $exception = $this->failedCall(
            new Response(404, ['Content-Type' => 'application/json'], '{"kind":"Status","apiVersion":"v1","status":"Failure","reason":"NotFound","code":0}')
        );

        $this->assertSame(404, $exception->getCode());
        $this->assertSame(
            ['kind' => 'Status', 'apiVersion' => 'v1', 'status' => 'Failure', 'reason' => 'NotFound', 'code' => 0],
            $exception->getPayload()
        );
    }

    private function failedCall(ResponseInterface $response): KubernetesAPIException
    {
        try {
            $this->clusterRespondingWith($response)->call('GET', '/apis/snapshot.storage.k8s.io/v1/namespaces/default/volumesnapshots/branch');
        } catch (KubernetesAPIException $exception) {
            return $exception;
        }

        $this->fail('Expected a KubernetesAPIException.');
    }
}
