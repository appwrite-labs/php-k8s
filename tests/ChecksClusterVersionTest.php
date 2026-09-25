<?php

namespace RenokiCo\PhpK8s\Test;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class ChecksClusterVersionTest extends TestCase
{
    public function test_check_cluster_version(): void
    {
        $this->assertFalse($this->cluster->olderThan('1.18.0'));
        $this->assertTrue($this->cluster->newerThan('1.18.0'));
        $this->assertFalse($this->cluster->newerThan('2.0.0'));
        $this->assertTrue($this->cluster->olderThan('2.0.0'));
    }

    public function test_plain_text_version_error_keeps_http_status_and_chains_guzzle_exception(): void
    {
        $cluster = $this->clusterRespondingWith(
            new Response(403, ['Content-Type' => 'text/plain; charset=utf-8'], "Forbidden\n")
        );

        try {
            $cluster->newerThan('1.18.0');
            $this->fail('Expected a KubernetesAPIException.');
        } catch (KubernetesAPIException $exception) {
            $this->assertSame(403, $exception->getCode());
            $this->assertNull($exception->getPayload());
            $this->assertInstanceOf(ClientException::class, $exception->getPrevious());
        }
    }
}
