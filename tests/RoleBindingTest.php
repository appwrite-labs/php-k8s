<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\Kinds\K8sRoleBinding;
use RenokiCo\PhpK8s\ResourcesList;

class RoleBindingTest extends TestCase
{
    public function test_role_binding_build(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $k8sRole = $this->cluster->role()
            ->setName('admin')
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $k8sRoleBinding = $this->cluster->roleBinding()
            ->setName('user-binding')
            ->setLabels(['tier' => 'backend'])
            ->setRole($k8sRole)
            ->addSubjects([$subject])
            ->setSubjects([$subject]);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'Role', 'name' => 'admin'], $k8sRoleBinding->getRole());
    }

    public function test_role_binding_from_yaml(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $this->cluster->role()
            ->setName('admin')
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $rb = $this->cluster->fromYamlFile(__DIR__.'/yaml/rolebinding.yaml');

        $this->assertEquals('rbac.authorization.k8s.io/v1', $rb->getApiVersion());
        $this->assertEquals('user-binding', $rb->getName());
        $this->assertEquals(['tier' => 'backend'], $rb->getLabels());
        $this->assertEquals([$subject], $rb->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'Role', 'name' => 'admin'], $rb->getRole());
    }

    public function test_role_binding_api_interaction(): void
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

        $k8sRole = $this->cluster->role()
            ->setName('admin')
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $rb = $this->cluster->roleBinding()
            ->setName('user-binding')
            ->setLabels(['tier' => 'backend'])
            ->setRole($k8sRole)
            ->addSubjects([$subject])
            ->setSubjects([$subject]);

        $this->assertFalse($rb->isSynced());
        $this->assertFalse($rb->exists());

        $rb = $rb->createOrUpdate();
        $k8sRole->createOrUpdate();

        $this->assertTrue($rb->isSynced());
        $this->assertTrue($rb->exists());

        $this->assertInstanceOf(K8sRoleBinding::class, $rb);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $rb->getApiVersion());
        $this->assertEquals('user-binding', $rb->getName());
        $this->assertEquals(['tier' => 'backend'], $rb->getLabels());
        $this->assertEquals([$subject], $rb->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'Role', 'name' => 'admin'], $rb->getRole());
    }

    public function runGetAllTests(): void
    {
        $allRoleBindings = $this->cluster->getAllRoleBindings();

        $this->assertInstanceOf(ResourcesList::class, $allRoleBindings);

        foreach ($allRoleBindings as $allRoleBinding) {
            $this->assertInstanceOf(K8sRoleBinding::class, $allRoleBinding);

            $this->assertNotNull($allRoleBinding->getName());
        }
    }

    public function runGetTests(): void
    {
        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $this->cluster->getRoleByName('admin');
        $k8sRoleBinding = $this->cluster->getRoleBindingByName('user-binding');

        $this->assertInstanceOf(K8sRoleBinding::class, $k8sRoleBinding);

        $this->assertTrue($k8sRoleBinding->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'Role', 'name' => 'admin'], $k8sRoleBinding->getRole());
    }

    public function runUpdateTests(): void
    {
        $this->cluster->getRoleByName('admin');
        $k8sRoleBinding = $this->cluster->getRoleBindingByName('user-binding');

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-2');

        $this->assertTrue($k8sRoleBinding->isSynced());

        $k8sRoleBinding->setSubjects([$subject]);

        $k8sRoleBinding->createOrUpdate();

        $this->assertTrue($k8sRoleBinding->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'Role', 'name' => 'admin'], $k8sRoleBinding->getRole());
    }

    public function runDeletionTests(): void
    {
        $k8sRole = $this->cluster->getRoleByName('admin');
        $k8sRoleBinding = $this->cluster->getRoleBindingByName('user-binding');

        $this->assertTrue($k8sRole->delete());
        $this->assertTrue($k8sRoleBinding->delete());

        while ($k8sRole->exists()) {
            sleep(1);
        }

        while ($k8sRoleBinding->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getRoleByName('admin');
        $this->cluster->getRoleBindingByName('user-binding');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->roleBinding()->watchAll(function ($type, $role) {
            if ($role->getName() === 'user-binding') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->roleBinding()->watchByName('user-binding', fn($type, $role): bool => $role->getName() === 'user-binding', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
