<?php

namespace RenokiCo\PhpK8s\Test;

use Exception;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class MakesHttpCallsIntegrationTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('CI') && ! $this->isClusterAvailable()) {
            $this->markTestSkipped('Integration tests require a live Kubernetes cluster');
        }
    }

    private function isClusterAvailable(): bool
    {
        try {
            $this->cluster->getAllNamespaces();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function test_unserved_api_group_answers_not_found(): void
    {
        try {
            $this->cluster->call('GET', '/apis/unserved.php-k8s.invalid/v1/namespaces/default/widgets/missing');
            $this->fail('Expected a KubernetesAPIException for an API group the cluster does not serve.');
        } catch (KubernetesAPIException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
    }
}
