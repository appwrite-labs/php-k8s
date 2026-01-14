<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\Kinds\K8sRole;
use RenokiCo\PhpK8s\ResourcesList;

class RoleTest extends TestCase
{
    public function test_role_build(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $k8sRole = $this->cluster->role()
            ->setName('admin')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRole->getApiVersion());
        $this->assertEquals('admin', $k8sRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRole->getLabels());
        $this->assertEquals([$rule], $k8sRole->getRules());
    }

    public function test_role_from_yaml(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $role = $this->cluster->fromYamlFile(__DIR__.'/yaml/role.yaml');

        $this->assertEquals('rbac.authorization.k8s.io/v1', $role->getApiVersion());
        $this->assertEquals('admin', $role->getName());
        $this->assertEquals(['tier' => 'backend'], $role->getLabels());
        $this->assertEquals([$rule], $role->getRules());
    }

    public function test_role_api_interaction(): void
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

        $role = $this->cluster->role()
            ->setName('admin')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $this->assertFalse($role->isSynced());
        $this->assertFalse($role->exists());

        $role = $role->createOrUpdate();

        $this->assertTrue($role->isSynced());
        $this->assertTrue($role->exists());

        $this->assertInstanceOf(K8sRole::class, $role);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $role->getApiVersion());
        $this->assertEquals('admin', $role->getName());
        $this->assertEquals(['tier' => 'backend'], $role->getLabels());
        $this->assertEquals([$rule], $role->getRules());
    }

    public function runGetAllTests(): void
    {
        $allRoles = $this->cluster->getAllRoles();

        $this->assertInstanceOf(ResourcesList::class, $allRoles);

        foreach ($allRoles as $allRole) {
            $this->assertInstanceOf(K8sRole::class, $allRole);

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

        $k8sRole = $this->cluster->getRoleByName('admin');

        $this->assertInstanceOf(K8sRole::class, $k8sRole);

        $this->assertTrue($k8sRole->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRole->getApiVersion());
        $this->assertEquals('admin', $k8sRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRole->getLabels());
        $this->assertEquals([$rule], $k8sRole->getRules());
    }

    public function runUpdateTests(): void
    {
        $k8sRole = $this->cluster->getRoleByName('admin');

        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class])
            ->addResourceNames(['pod-name'])
            ->addVerbs(['get', 'list']);

        $this->assertTrue($k8sRole->isSynced());

        $k8sRole->setRules([$rule]);

        $k8sRole->createOrUpdate();

        $this->assertTrue($k8sRole->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRole->getApiVersion());
        $this->assertEquals('admin', $k8sRole->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRole->getLabels());
        $this->assertEquals([$rule], $k8sRole->getRules());
    }

    public function runDeletionTests(): void
    {
        $k8sRole = $this->cluster->getRoleByName('admin');

        $this->assertTrue($k8sRole->delete());

        while ($k8sRole->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getRoleByName('admin');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->role()->watchAll(function ($type, $role) {
            if ($role->getName() === 'admin') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->role()->watchByName('admin', fn($type, $role): bool => $role->getName() === 'admin', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
