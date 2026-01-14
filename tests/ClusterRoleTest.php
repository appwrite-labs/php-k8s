<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sClusterRole;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class ClusterRoleTest extends TestCase
{
    public function test_cluster_role_build(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $k8sClusterRole = $this->cluster->clusterRole()
            ->setName('admin-cr')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRole->getApiVersion());
        $this->assertEquals('admin-cr', $k8sClusterRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRole->getLabels());
        $this->assertEquals([$rule], $k8sClusterRole->getRules());
    }

    public function test_cluster_role_from_yaml(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $cr = $this->cluster->fromYamlFile(__DIR__.'/yaml/clusterrole.yaml');

        $this->assertEquals('rbac.authorization.k8s.io/v1', $cr->getApiVersion());
        $this->assertEquals('admin-cr', $cr->getName());
        $this->assertEquals([$rule], $cr->getRules());
    }

    public function test_cluster_role_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runCreationTests(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $cr = $this->cluster->clusterRole()
            ->setName('admin-cr')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $this->assertFalse($cr->isSynced());
        $this->assertFalse($cr->exists());

        $cr = $cr->createOrUpdate();

        $this->assertTrue($cr->isSynced());
        $this->assertTrue($cr->exists());

        $this->assertInstanceOf(K8sClusterRole::class, $cr);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $cr->getApiVersion());
        $this->assertEquals('admin-cr', $cr->getName());
        $this->assertEquals(['tier' => 'backend'], $cr->getLabels());
        $this->assertEquals([$rule], $cr->getRules());
    }

    public function runGetAllTests(): void
    {
        $allRoles = $this->cluster->getAllRoles();

        $this->assertInstanceOf(ResourcesList::class, $allRoles);

        foreach ($allRoles as $allRole) {
            $this->assertInstanceOf(K8sClusterRole::class, $allRole);

            $this->assertNotNull($allRole->getName());
        }
    }

    public function runGetTests(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $k8sClusterRole = $this->cluster->getClusterRoleByName('admin-cr');

        $this->assertInstanceOf(K8sClusterRole::class, $k8sClusterRole);

        $this->assertTrue($k8sClusterRole->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRole->getApiVersion());
        $this->assertEquals('admin-cr', $k8sClusterRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRole->getLabels());
        $this->assertEquals([$rule], $k8sClusterRole->getRules());
    }

    public function runUpdateTests(): void
    {
        $k8sClusterRole = $this->cluster->getClusterRoleByName('admin-cr');

        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class])
            ->addResourceNames(['pod-name'])
            ->addVerbs(['get', 'list']);

        $this->assertTrue($k8sClusterRole->isSynced());

        $k8sClusterRole->setRules([$rule]);

        $k8sClusterRole->createOrUpdate();

        $this->assertTrue($k8sClusterRole->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRole->getApiVersion());
        $this->assertEquals('admin-cr', $k8sClusterRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRole->getLabels());
        $this->assertEquals([$rule], $k8sClusterRole->getRules());
    }

    public function runDeletionTests(): void
    {
        $k8sClusterRole = $this->cluster->getClusterRoleByName('admin-cr');

        $this->assertTrue($k8sClusterRole->delete());

        while ($k8sClusterRole->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getClusterRoleByName('admin-cr');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->clusterRole()->watchAll(function ($type, $cr) {
            if ($cr->getName() === 'admin-cr') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->clusterRole()->watchByName('admin-cr', fn($type, $cr): bool => $cr->getName() === 'admin-cr', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
