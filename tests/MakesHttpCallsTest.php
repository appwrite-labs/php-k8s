<?php

namespace RenokiCo\PhpK8s\Test;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class MakesHttpCallsTest extends TestCase
{
    private const string SNAPSHOT_PATH = '/apis/snapshot.storage.k8s.io/v1/namespaces/default/volumesnapshots/branch';

    private const string UNSERVED_PATH = '/apis/unserved.php-k8s.invalid/v1/namespaces/default/widgets/missing';

    public function test_plain_text_error_keeps_http_status_and_chains_guzzle_exception(): void
    {
        $exception = $this->failedCall(
            new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], "404 page not found\n")
        );

        $this->assertSame(404, $exception->getCode());
        $this->assertNull($exception->getPayload());
        $this->assertInstanceOf(ClientException::class, $exception->getPrevious());
    }

    #[DataProvider('statuses')]
    public function test_json_status_error_keeps_payload_code(array $status): void
    {
        $exception = $this->failedCall(
            new Response($status['code'], ['Content-Type' => 'application/json'], json_encode($status))
        );

        $this->assertSame($status['code'], $exception->getCode());
        $this->assertSame($status, $exception->getPayload());
    }

    public static function statuses(): iterable
    {
        yield 'not found' => [[
            'kind' => 'Status',
            'apiVersion' => 'v1',
            'metadata' => [],
            'status' => 'Failure',
            'message' => 'volumesnapshots.snapshot.storage.k8s.io "branch" not found',
            'reason' => 'NotFound',
            'details' => ['name' => 'branch', 'group' => 'snapshot.storage.k8s.io', 'kind' => 'volumesnapshots'],
            'code' => 404,
        ]];

        yield 'conflict' => [[
            'kind' => 'Status',
            'apiVersion' => 'v1',
            'metadata' => [],
            'status' => 'Failure',
            'message' => 'volumesnapshots.snapshot.storage.k8s.io "branch" already exists',
            'reason' => 'AlreadyExists',
            'details' => ['name' => 'branch', 'group' => 'snapshot.storage.k8s.io', 'kind' => 'volumesnapshots'],
            'code' => 409,
        ]];
    }

    public function test_unserved_api_group_answers_not_found(): void
    {
        try {
            $this->cluster->call('GET', self::UNSERVED_PATH);
            $this->fail('Expected a KubernetesAPIException for an API group the cluster does not serve.');
        } catch (KubernetesAPIException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
    }

    private function failedCall(ResponseInterface $response): KubernetesAPIException
    {
        try {
            $this->clusterRespondingWith($response)->call('GET', self::SNAPSHOT_PATH);
        } catch (KubernetesAPIException $exception) {
            return $exception;
        }

        $this->fail('Expected a KubernetesAPIException.');
    }
}
