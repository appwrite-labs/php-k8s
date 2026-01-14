<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sNamespace;
use RenokiCo\PhpK8s\ResourcesList;

class NamespaceTest extends TestCase
{
    public function test_namespace_build(): void
    {
        $k8sNamespace = $this->cluster->namespace()
            ->setName('production')
            ->setLabels(['tier' => 'backend']);

        $this->assertEquals('v1', $k8sNamespace->getApiVersion());
        $this->assertEquals('production', $k8sNamespace->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sNamespace->getLabels());
    }

    public function test_namespace_from_yaml(): void
    {
        $ns = $this->cluster->fromYamlFile(__DIR__.'/yaml/namespace.yaml');

        $this->assertEquals('v1', $ns->getApiVersion());
        $this->assertEquals('production', $ns->getName());
        $this->assertEquals(['tier' => 'backend'], $ns->getLabels());
    }

    public function test_namespace_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runGetAllTests(): void
    {
        $allNamespaces = $this->cluster->getAllNamespaces();

        $this->assertInstanceOf(ResourcesList::class, $allNamespaces);

        foreach ($allNamespaces as $allNamespace) {
            $this->assertInstanceOf(K8sNamespace::class, $allNamespace);

            $this->assertNotNull($allNamespace->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sNamespace = $this->cluster->getNamespaceByName('production');

        $this->assertInstanceOf(K8sNamespace::class, $k8sNamespace);

        $this->assertTrue($k8sNamespace->isSynced());

        $this->assertEquals('production', $k8sNamespace->getName());

        $this->assertEquals([
            'kubernetes.io/metadata.name' => 'production',
            'tier' => 'backend',
        ], $k8sNamespace->getLabels());
    }

    public function runCreationTests(): void
    {
        $ns = $this->cluster->namespace()
            ->setName('production')
            ->setLabels(['tier' => 'backend']);

        $this->assertFalse($ns->isSynced());
        $this->assertFalse($ns->exists());

        $ns = $ns->createOrUpdate();

        $this->assertTrue($ns->isSynced());
        $this->assertTrue($ns->exists());

        $this->assertInstanceOf(K8sNamespace::class, $ns);

        $this->assertEquals('production', $ns->getName());

        $this->assertEquals([
            'kubernetes.io/metadata.name' => 'production',
            'tier' => 'backend',
        ], $ns->getLabels());

        $ns->refresh();

        $this->assertTrue($ns->isActive());
        $this->assertFalse($ns->isTerminating());
    }

    public function runUpdateTests(): void
    {
        $k8sNamespace = $this->cluster->getNamespaceByName('production');

        $this->assertTrue($k8sNamespace->isSynced());

        $k8sNamespace->createOrUpdate();

        $this->assertTrue($k8sNamespace->isSynced());
    }

    public function runDeletionTests(): void
    {
        $k8sNamespace = $this->cluster->getNamespaceByName('production');

        $this->assertTrue($k8sNamespace->delete());

        while ($k8sNamespace->exists()) {
            dump(sprintf('Awaiting for namespace %s to be deleted...', $k8sNamespace->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getNamespaceByName('production');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->namespace()->watchAll(function ($type, $namespace) {
            if ($namespace->getName() === 'production') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->namespace()->watchByName('production', fn($type, $namespace): bool => $namespace->getName() === 'production', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
