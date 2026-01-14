<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Kinds\K8sNode;
use RenokiCo\PhpK8s\ResourcesList;

class NodeTest extends TestCase
{
    public function test_node_api_interaction(): void
    {
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
    }

    public function runGetAllTests(): void
    {
        $allNodes = $this->cluster->getAllNodes();

        $this->assertInstanceOf(ResourcesList::class, $allNodes);

        foreach ($allNodes as $allNode) {
            $this->assertInstanceOf(K8sNode::class, $allNode);

            $this->assertNotNull($allNode->getName());
        }
    }

    public function runGetTests(): void
    {
        $nodeName = $this->cluster->getAllNodes()->first()->getName();

        $k8sNode = $this->cluster->getNodeByName($nodeName);

        $this->assertInstanceOf(K8sNode::class, $k8sNode);

        $this->assertTrue($k8sNode->isSynced());

        //$this->assertEquals('minikube', $node->getName());
        $this->assertNotEquals([], $k8sNode->getInfo());
        $this->assertTrue(is_array($k8sNode->getImages()));
        $this->assertNotEquals([], $k8sNode->getCapacity());
        $this->assertNotEquals([], $k8sNode->getAllocatableInfo());
    }

    public function runWatchAllTests(): void
    {
        $nodeName = $this->cluster->getAllNodes()->first()->getName();

        $watch = $this->cluster->node()->watchAll(function ($type, $node) use ($nodeName) {
            if ($node->getName() === $nodeName) {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $nodeName = $this->cluster->getAllNodes()->first()->getName();

        $watch = $this->cluster->node()->watchByName($nodeName, fn($type, $node): bool => $node->getName() === $nodeName, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
