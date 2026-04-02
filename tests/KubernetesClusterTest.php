<?php

namespace RenokiCo\PhpK8s\Test;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RenokiCo\PhpK8s\Enums\Operation;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\KubernetesCluster;

class KubernetesClusterTest extends TestCase
{
    public function test_log_operations_return_raw_json_logs_as_strings(): void
    {
        $expectedLogs = '{"status":200,"message":"ok","container":{"size":123}}';

        $cluster = new class('http://127.0.0.1:8080', new Response(200, [], $expectedLogs)) extends KubernetesCluster
        {
            public function __construct(?string $url, private readonly ResponseInterface $response)
            {
                parent::__construct($url);
            }

            public function call(string $method, string $path, string $payload = '', array $query = ['pretty' => 1], array $options = []): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $cluster
            ->setResourceClass(K8sPod::class)
            ->runOperation(Operation::LOG, '/api/v1/namespaces/default/pods/example/log', '');

        $this->assertSame($expectedLogs, $result);
    }

    public function test_non_log_operations_still_hydrate_resources_from_json(): void
    {
        $cluster = new class('http://127.0.0.1:8080', new Response(200, [], '{"kind":"Pod","metadata":{"name":"example"}}')) extends KubernetesCluster
        {
            public function __construct(?string $url, private readonly ResponseInterface $response)
            {
                parent::__construct($url);
            }

            public function call(string $method, string $path, string $payload = '', array $query = ['pretty' => 1], array $options = []): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $cluster
            ->setResourceClass(K8sPod::class)
            ->runOperation(Operation::GET, '/api/v1/namespaces/default/pods/example', '');

        $this->assertInstanceOf(K8sPod::class, $result);
        $this->assertSame('example', $result->getName());
    }
}
