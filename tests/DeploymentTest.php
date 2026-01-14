<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class DeploymentTest extends TestCase
{
    public function test_deployment_build(): void
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

        $k8sDeployment = $this->cluster->deployment()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setReplicas(3)
            ->setTemplate($k8sPod);

        $this->assertEquals('apps/v1', $k8sDeployment->getApiVersion());
        $this->assertEquals('mysql', $k8sDeployment->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDeployment->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sDeployment->getAnnotations());
        $this->assertEquals(3, $k8sDeployment->getReplicas());
        $this->assertEquals($k8sPod->getName(), $k8sDeployment->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $k8sDeployment->getTemplate());
    }

    public function test_deployment_from_yaml(): void
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

        $dep = $this->cluster->fromYamlFile(__DIR__.'/yaml/deployment.yaml');

        $this->assertEquals('apps/v1', $dep->getApiVersion());
        $this->assertEquals('mysql', $dep->getName());
        $this->assertEquals(['tier' => 'backend'], $dep->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $dep->getAnnotations());
        $this->assertEquals(3, $dep->getReplicas());
        $this->assertEquals($k8sPod->getName(), $dep->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $dep->getTemplate());
    }

    public function test_deployment_api_interaction(): void
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
            ->setLabels(['tier' => 'backend', 'deployment-name' => 'mysql'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setContainers([$mysql]);

        $dep = $this->cluster->deployment()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setReplicas(1)
            ->setUpdateStrategy('RollingUpdate')
            ->setMinReadySeconds(0)
            ->setTemplate($k8sPod);

        $this->assertFalse($dep->isSynced());
        $this->assertFalse($dep->exists());

        $dep = $dep->createOrUpdate();

        $this->assertTrue($dep->isSynced());
        $this->assertTrue($dep->exists());

        $this->assertInstanceOf(K8sDeployment::class, $dep);

        $this->assertEquals('apps/v1', $dep->getApiVersion());
        $this->assertEquals('mysql', $dep->getName());
        $this->assertEquals(['tier' => 'backend'], $dep->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $dep->getAnnotations());
        $this->assertEquals(1, $dep->getReplicas());
        $this->assertEquals(0, $dep->getMinReadySeconds());
        $this->assertEquals($k8sPod->getName(), $dep->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $dep->getTemplate());

        while (! $dep->allPodsAreRunning()) {
            dump(sprintf('Waiting for pods of %s to be up and running...', $dep->getName()));
            sleep(1);
        }

        K8sDeployment::selectPods(function ($dep): array {
            $this->assertInstanceOf(K8sDeployment::class, $dep);

            return ['tier' => 'backend'];
        });

        $pods = $dep->getPods();
        $this->assertTrue($pods->count() > 0);

        K8sDeployment::resetPodsSelector();

        $pods = $dep->getPods();
        $this->assertTrue($pods->count() > 0);

        foreach ($pods as $pod) {
            $this->assertInstanceOf(K8sPod::class, $pod);
        }

        $dep->refresh();

        while ($dep->getReadyReplicasCount() === 0) {
            dump(sprintf('Waiting for pods of %s to have ready replicas...', $dep->getName()));
            sleep(1);
            $dep->refresh();
        }

        $this->assertEquals(1, $dep->getAvailableReplicasCount());
        $this->assertEquals(1, $dep->getReadyReplicasCount());
        $this->assertEquals(1, $dep->getDesiredReplicasCount());
        $this->assertEquals(0, $dep->getUnavailableReplicasCount());

        $this->assertTrue(is_array($dep->getConditions()));
    }

    public function runGetAllTests(): void
    {
        $allDeployments = $this->cluster->getAllDeployments();

        $this->assertInstanceOf(ResourcesList::class, $allDeployments);

        foreach ($allDeployments as $allDeployment) {
            $this->assertInstanceOf(K8sDeployment::class, $allDeployment);

            $this->assertNotNull($allDeployment->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sDeployment = $this->cluster->getDeploymentByName('mysql');

        $this->assertInstanceOf(K8sDeployment::class, $k8sDeployment);

        $this->assertTrue($k8sDeployment->isSynced());

        $this->assertEquals('apps/v1', $k8sDeployment->getApiVersion());
        $this->assertEquals('mysql', $k8sDeployment->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDeployment->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes', 'deployment.kubernetes.io/revision' => '1'], $k8sDeployment->getAnnotations());
        $this->assertEquals(1, $k8sDeployment->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $k8sDeployment->getTemplate());
    }

    public function attachPodAutoscaler(): void
    {
        $k8sDeployment = $this->cluster->getDeploymentByName('mysql');

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $k8sResource = $this->cluster->horizontalPodAutoscaler()
            ->setName('deploy-mysql')
            ->setResource($k8sDeployment)
            ->addMetrics([$resourceMetric])
            ->setMetrics([$resourceMetric])
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
        $k8sDeployment = $this->cluster->getDeploymentByName('mysql');

        $this->assertTrue($k8sDeployment->isSynced());

        $k8sDeployment->setAnnotations([]);

        $k8sDeployment->createOrUpdate();

        $this->assertTrue($k8sDeployment->isSynced());

        $this->assertEquals('apps/v1', $k8sDeployment->getApiVersion());
        $this->assertEquals('mysql', $k8sDeployment->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDeployment->getLabels());
        $this->assertEquals([], $k8sDeployment->getAnnotations());
        $this->assertEquals(2, $k8sDeployment->getReplicas());

        $this->assertInstanceOf(K8sPod::class, $k8sDeployment->getTemplate());
    }

    public function runDeletionTests(): void
    {
        $k8sDeployment = $this->cluster->getDeploymentByName('mysql');
        $k8sHorizontalPodAutoscaler = $this->cluster->getHorizontalPodAutoscalerByName('deploy-mysql');

        $this->assertTrue($k8sDeployment->delete());
        $this->assertTrue($k8sHorizontalPodAutoscaler->delete());

        while ($k8sHorizontalPodAutoscaler->exists()) {
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to be deleted...', $k8sHorizontalPodAutoscaler->getName()));
            sleep(1);
        }

        while ($k8sDeployment->exists()) {
            dump(sprintf('Awaiting for deployment %s to be deleted...', $k8sDeployment->getName()));
            sleep(1);
        }

        while ($k8sDeployment->getPods()->count() > 0) {
            dump(sprintf("Awaiting for deployment %s's pods to be deleted...", $k8sDeployment->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getDeploymentByName('mysql');
        $this->cluster->getHorizontalPodAutoscalerByName('deploy-mysql');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->deployment()->watchAll(function ($type, $dep) {
            if ($dep->getName() === 'mysql') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->deployment()->watchByName('mysql', fn($type, $dep): bool => $dep->getName() === 'mysql', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runScalingTests(): void
    {
        $k8sDeployment = $this->cluster->getDeploymentByName('mysql');

        $k8sScale = $k8sDeployment->scale(2);

        while ($k8sDeployment->getReadyReplicasCount() < 2 || $k8sScale->getReplicas() < 2) {
            dump(sprintf('Awaiting for deployment %s to scale to 2 replicas...', $k8sDeployment->getName()));
            $k8sScale->refresh();
            $k8sDeployment->refresh();
            sleep(1);
        }

        $this->assertEquals(2, $k8sDeployment->getReadyReplicasCount());
        $this->assertEquals(2, $k8sScale->getReplicas());
        $this->assertCount(2, $k8sDeployment->getPods());
    }
}
