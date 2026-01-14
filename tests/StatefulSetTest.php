<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\Kinds\K8sStatefulSet;
use RenokiCo\PhpK8s\ResourcesList;

class StatefulSetTest extends TestCase
{
    public function test_stateful_set_build(): void
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ]);

        $k8sPod = $this->cluster->pod()
            ->setName('mysql')
            ->setContainers([$mysql]);

        $k8sService = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ]);

        $k8sStorageClass = $this->cluster->getStorageClassByName('standard');

        $k8sPersistentVolumeClaim = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass($k8sStorageClass);

        $k8sStatefulSet = $this->cluster->statefulSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setReplicas(3)
            ->setService($k8sService)
            ->setTemplate($k8sPod)
            ->setUpdateStrategy('RollingUpdate')
            ->setVolumeClaims([$k8sPersistentVolumeClaim]);

        $this->assertEquals('apps/v1', $k8sStatefulSet->getApiVersion());
        $this->assertEquals('mysql', $k8sStatefulSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sStatefulSet->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sStatefulSet->getAnnotations());
        $this->assertEquals(3, $k8sStatefulSet->getReplicas());
        $this->assertEquals($k8sService->getName(), $k8sStatefulSet->getService());
        $this->assertEquals($k8sPod->getName(), $k8sStatefulSet->getTemplate()->getName());
        $this->assertEquals($k8sPersistentVolumeClaim->getName(), $k8sStatefulSet->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $k8sStatefulSet->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $k8sStatefulSet->getVolumeClaims()[0]);
    }

    public function test_stateful_set_from_yaml(): void
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ]);

        $k8sPod = $this->cluster->pod()
            ->setName('mysql')
            ->setContainers([$mysql]);

        $k8sService = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ]);

        $k8sStorageClass = $this->cluster->getStorageClassByName('standard');

        $k8sPersistentVolumeClaim = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass($k8sStorageClass);

        $sts = $this->cluster->fromYamlFile(__DIR__.'/yaml/statefulset.yaml');

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(3, $sts->getReplicas());
        $this->assertEquals($k8sService->getName(), $sts->getService());
        $this->assertEquals($k8sPod->getName(), $sts->getTemplate()->getName());
        $this->assertEquals($k8sPersistentVolumeClaim->getName(), $sts->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);
    }

    public function test_stateful_set_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->attachPodAutoscaler();
        $this->runScalingTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function runCreationTests(): void
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ])
            ->addPort(3307, 'TCP', 'mysql-alt')
            ->setEnv(['MYSQL_ROOT_PASSWORD' => 'test']);

        $k8sPod = $this->cluster->pod()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend', 'statefulset-name' => 'mysql'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setContainers([$mysql]);

        $k8sService = $this->cluster->service()
            ->setName('mysql')
            ->setPorts([
                ['protocol' => 'TCP', 'port' => 3306, 'targetPort' => 3306],
            ])
            ->createOrUpdate();

        $k8sStorageClass = $this->cluster->getStorageClassByName('standard');

        $k8sPersistentVolumeClaim = $this->cluster->persistentVolumeClaim()
            ->setName('mysql-pvc')
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass($k8sStorageClass);

        $sts = $this->cluster->statefulSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setReplicas(1)
            ->setService($k8sService)
            ->setTemplate($k8sPod)
            ->setVolumeClaims([$k8sPersistentVolumeClaim]);

        $this->assertFalse($sts->isSynced());
        $this->assertFalse($sts->exists());

        $sts = $sts->createOrUpdate();

        $this->assertTrue($sts->isSynced());
        $this->assertTrue($sts->exists());

        $this->assertInstanceOf(K8sStatefulSet::class, $sts);

        $this->assertEquals('apps/v1', $sts->getApiVersion());
        $this->assertEquals('mysql', $sts->getName());
        $this->assertEquals(['tier' => 'backend'], $sts->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $sts->getAnnotations());
        $this->assertEquals(1, $sts->getReplicas());
        $this->assertEquals($k8sService->getName(), $sts->getService());
        $this->assertEquals($k8sPod->getName(), $sts->getTemplate()->getName());
        $this->assertEquals($k8sPersistentVolumeClaim->getName(), $sts->getVolumeClaims()[0]->getName());

        $this->assertInstanceOf(K8sPod::class, $sts->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $sts->getVolumeClaims()[0]);

        while (! $sts->allPodsAreRunning()) {
            dump(sprintf('Waiting for pods of %s to be up and running...', $sts->getName()));
            sleep(1);
        }

        K8sStatefulSet::selectPods(function ($sts): array {
            $this->assertInstanceOf(K8sStatefulSet::class, $sts);

            return ['tier' => 'backend'];
        });

        $pods = $sts->getPods();
        $this->assertTrue($pods->count() > 0);

        K8sStatefulSet::resetPodsSelector();

        $pods = $sts->getPods();
        $this->assertTrue($pods->count() > 0);

        foreach ($pods as $pod) {
            $this->assertInstanceOf(K8sPod::class, $pod);
        }

        $sts->refresh();

        while ($sts->getReadyReplicasCount() === 0) {
            dump(sprintf('Waiting for pods of %s to have ready replicas...', $sts->getName()));
            sleep(1);
            $sts->refresh();
        }

        $this->assertEquals(1, $sts->getCurrentReplicasCount());
        $this->assertEquals(1, $sts->getReadyReplicasCount());
        $this->assertEquals(1, $sts->getDesiredReplicasCount());

        $this->assertTrue(is_array($sts->getConditions()));
    }

    public function runGetAllTests(): void
    {
        $allStatefulSets = $this->cluster->getAllStatefulSets();

        $this->assertInstanceOf(ResourcesList::class, $allStatefulSets);

        foreach ($allStatefulSets as $allStatefulSet) {
            $this->assertInstanceOf(K8sStatefulSet::class, $allStatefulSet);

            $this->assertNotNull($allStatefulSet->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sStatefulSet = $this->cluster->getStatefulSetByName('mysql');

        $this->assertInstanceOf(K8sStatefulSet::class, $k8sStatefulSet);

        $this->assertTrue($k8sStatefulSet->isSynced());

        $this->assertEquals('apps/v1', $k8sStatefulSet->getApiVersion());
        $this->assertEquals('mysql', $k8sStatefulSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sStatefulSet->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sStatefulSet->getAnnotations());
        $this->assertEquals(1, $k8sStatefulSet->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $k8sStatefulSet->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $k8sStatefulSet->getVolumeClaims()[0]);
    }

    public function attachPodAutoscaler(): void
    {
        $k8sStatefulSet = $this->cluster->getStatefulSetByName('mysql');

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $resourceObject = K8s::object()
            ->setResource($k8sStatefulSet->getServiceInstance())
            ->setMetric('packets-per-second')
            ->averageValue('1k');

        $k8sResource = $this->cluster->horizontalPodAutoscaler()
            ->setName('sts-mysql')
            ->setResource($k8sStatefulSet)
            ->addMetrics([$resourceMetric, $resourceObject])
            ->min(1)
            ->max(10)
            ->create();

        while ($k8sResource->getCurrentReplicasCount() < 1) {
            $k8sResource->refresh();
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to read the current replicas...', $k8sResource->getName()));
            sleep(1);
        }

        $this->assertEquals(1, $k8sResource->getCurrentReplicasCount());
    }

    public function runUpdateTests(): void
    {
        $k8sStatefulSet = $this->cluster->getStatefulSetByName('mysql');

        $this->assertTrue($k8sStatefulSet->isSynced());

        $k8sStatefulSet->setAnnotations([]);

        $k8sStatefulSet->createOrUpdate();

        $this->assertTrue($k8sStatefulSet->isSynced());

        $this->assertEquals('apps/v1', $k8sStatefulSet->getApiVersion());
        $this->assertEquals('mysql', $k8sStatefulSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sStatefulSet->getLabels());
        $this->assertEquals([], $k8sStatefulSet->getAnnotations());
        $this->assertEquals(2, $k8sStatefulSet->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $k8sStatefulSet->getTemplate());
        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $k8sStatefulSet->getVolumeClaims()[0]);
    }

    public function runDeletionTests(): void
    {
        $k8sStatefulSet = $this->cluster->getStatefulSetByName('mysql');
        $k8sHorizontalPodAutoscaler = $this->cluster->getHorizontalPodAutoscalerByName('sts-mysql');

        $this->assertTrue($k8sStatefulSet->delete());
        $this->assertTrue($k8sHorizontalPodAutoscaler->delete());

        while ($k8sHorizontalPodAutoscaler->exists()) {
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to be deleted...', $k8sHorizontalPodAutoscaler->getName()));
            sleep(1);
        }

        while ($k8sStatefulSet->exists()) {
            dump(sprintf('Awaiting for statefulset %s to be deleted...', $k8sStatefulSet->getName()));
            sleep(1);
        }

        while ($k8sStatefulSet->getPods()->count() > 0) {
            dump(sprintf("Awaiting for statefulset %s's pods to be deleted...", $k8sStatefulSet->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getStatefulSetByName('mysql');
        $this->cluster->getHorizontalPodAutoscalerByName('sts-mysql');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->statefulSet()->watchAll(function ($type, $sts) {
            if ($sts->getName() === 'mysql') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->statefulSet()->watchByName('mysql', fn($type, $sts): bool => $sts->getName() === 'mysql', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runScalingTests(): void
    {
        $k8sStatefulSet = $this->cluster->getStatefulSetByName('mysql');

        $k8sScale = $k8sStatefulSet->scale(2);

        while ($k8sStatefulSet->getReadyReplicasCount() < 2 || $k8sScale->getReplicas() < 2) {
            dump(sprintf('Awaiting for statefulset %s to scale to 2 replicas...', $k8sStatefulSet->getName()));
            $k8sScale->refresh();
            $k8sStatefulSet->refresh();
            sleep(1);
        }

        $this->assertEquals(2, $k8sStatefulSet->getReadyReplicasCount());
        $this->assertEquals(2, $k8sScale->getReplicas());
        $this->assertCount(2, $k8sStatefulSet->getPods());
    }
}
