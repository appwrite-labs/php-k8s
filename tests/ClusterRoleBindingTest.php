<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sClusterRoleBinding;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class ClusterRoleBindingTest extends TestCase
{
    public function test_cluster_role_binding_build(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $k8sClusterRole = $this->cluster->clusterRole()
            ->setName('admin-cr-for-binding')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $k8sClusterRoleBinding = $this->cluster->clusterRoleBinding()
            ->setName('user-binding')
            ->setLabels(['tier' => 'backend'])
            ->setRole($k8sClusterRole)
            ->addSubjects([$subject])
            ->setSubjects([$subject]);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sClusterRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sClusterRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'ClusterRole', 'name' => 'admin-cr-for-binding'], $k8sClusterRoleBinding->getRole());
    }

    public function test_cluster_role_binding_from_yaml(): void
    {
        $rule = K8s::rule()
            ->core()
            ->addResources([K8sPod::class, 'configmaps'])
            ->addResourceNames(['pod-name', 'configmap-name'])
            ->addVerbs(['get', 'list', 'watch']);

        $this->cluster->clusterRole()
            ->setName('admin-cr-for-binding')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $crb = $this->cluster->fromYamlFile(__DIR__.'/yaml/clusterrolebinding.yaml');

        $this->assertEquals('rbac.authorization.k8s.io/v1', $crb->getApiVersion());
        $this->assertEquals('user-binding', $crb->getName());
        $this->assertEquals(['tier' => 'backend'], $crb->getLabels());
        $this->assertEquals([$subject], $crb->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'ClusterRole', 'name' => 'admin-cr-for-binding'], $crb->getRole());
    }

    public function test_cluster_role_binding_api_interaction(): void
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

        $k8sClusterRole = $this->cluster->clusterRole()
            ->setName('admin-cr-for-binding')
            ->setLabels(['tier' => 'backend'])
            ->addRules([$rule]);

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $crb = $this->cluster->clusterRoleBinding()
            ->setName('user-binding')
            ->setLabels(['tier' => 'backend'])
            ->setRole($k8sClusterRole)
            ->addSubjects([$subject])
            ->setSubjects([$subject]);

        $this->assertFalse($crb->isSynced());
        $this->assertFalse($crb->exists());

        $crb = $crb->createOrUpdate();
        $k8sClusterRole->createOrUpdate();

        $this->assertTrue($crb->isSynced());
        $this->assertTrue($crb->exists());

        $this->assertInstanceOf(K8sClusterRoleBinding::class, $crb);

        $this->assertEquals('rbac.authorization.k8s.io/v1', $crb->getApiVersion());
        $this->assertEquals('user-binding', $crb->getName());
        $this->assertEquals(['tier' => 'backend'], $crb->getLabels());
        $this->assertEquals([$subject], $crb->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'ClusterRole', 'name' => 'admin-cr-for-binding'], $crb->getRole());
    }

    public function runGetAllTests(): void
    {
        $allClusterRoleBindings = $this->cluster->getAllClusterRoleBindings();

        $this->assertInstanceOf(ResourcesList::class, $allClusterRoleBindings);

        foreach ($allClusterRoleBindings as $allClusterRoleBinding) {
            $this->assertInstanceOf(K8sClusterRoleBinding::class, $allClusterRoleBinding);

            $this->assertNotNull($allClusterRoleBinding->getName());
        }
    }

    public function runGetTests(): void
    {
        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-1');

        $this->cluster->getClusterRoleByName('admin-cr-for-binding');
        $k8sClusterRoleBinding = $this->cluster->getClusterRoleBindingByName('user-binding');

        $this->assertInstanceOf(K8sClusterRoleBinding::class, $k8sClusterRoleBinding);

        $this->assertTrue($k8sClusterRoleBinding->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sClusterRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sClusterRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'ClusterRole', 'name' => 'admin-cr-for-binding'], $k8sClusterRoleBinding->getRole());
    }

    public function runUpdateTests(): void
    {
        $this->cluster->getClusterRoleByName('admin-cr-for-binding');
        $k8sClusterRoleBinding = $this->cluster->getClusterRoleBindingByName('user-binding');

        $subject = K8s::subject()
            ->setApiGroup('rbac.authorization.k8s.io')
            ->setKind('User')
            ->setName('user-2');

        $this->assertTrue($k8sClusterRoleBinding->isSynced());

        $k8sClusterRoleBinding->setSubjects([$subject]);

        $k8sClusterRoleBinding->createOrUpdate();

        $this->assertTrue($k8sClusterRoleBinding->isSynced());

        $this->assertEquals('rbac.authorization.k8s.io/v1', $k8sClusterRoleBinding->getApiVersion());
        $this->assertEquals('user-binding', $k8sClusterRoleBinding->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sClusterRoleBinding->getLabels());
        $this->assertEquals([$subject], $k8sClusterRoleBinding->getSubjects());
        $this->assertEquals(['apiGroup' => 'rbac.authorization.k8s.io', 'kind' => 'ClusterRole', 'name' => 'admin-cr-for-binding'], $k8sClusterRoleBinding->getRole());
    }

    public function runDeletionTests(): void
    {
        $k8sClusterRole = $this->cluster->getClusterRoleByName('admin-cr-for-binding');
        $k8sClusterRoleBinding = $this->cluster->getClusterRoleBindingByName('user-binding');

        $this->assertTrue($k8sClusterRole->delete());
        $this->assertTrue($k8sClusterRoleBinding->delete());

        while ($k8sClusterRole->exists()) {
            sleep(1);
        }

        while ($k8sClusterRoleBinding->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getClusterRoleByName('admin-cr-for-binding');
        $this->cluster->getClusterRoleBindingByName('user-binding');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->clusterRoleBinding()->watchAll(function ($type, $cr) {
            if ($cr->getName() === 'user-binding') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->clusterRoleBinding()->watchByName('user-binding', fn($type, $cr): bool => $cr->getName() === 'user-binding', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
