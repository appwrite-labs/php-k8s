<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sHorizontalPodAutoscaler;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class HorizontalPodAutoscalerTest extends TestCase
{
    public function test_horizontal_pod_autoscaler_build(): void
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

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $k8sHorizontalPodAutoscaler = $this->cluster->horizontalPodAutoscaler()
            ->setName('mysql-hpa')
            ->setLabels(['tier' => 'backend'])
            ->setResource($k8sDeployment)
            ->addMetrics([$resourceMetric])
            ->setMetrics([$resourceMetric])
            ->min(1)
            ->max(10);

        $this->assertEquals('autoscaling/v2', $k8sHorizontalPodAutoscaler->getApiVersion());
        $this->assertEquals('mysql-hpa', $k8sHorizontalPodAutoscaler->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sHorizontalPodAutoscaler->getLabels());
        $this->assertEquals([$resourceMetric->toArray()], $k8sHorizontalPodAutoscaler->getMetrics());
        $this->assertEquals(1, $k8sHorizontalPodAutoscaler->getMinReplicas());
        $this->assertEquals(10, $k8sHorizontalPodAutoscaler->getMaxReplicas());
    }

    public function test_horizontal_pod_autoscaler_from_yaml(): void
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

        $this->cluster->deployment()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setReplicas(3)
            ->setTemplate($k8sPod);

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $hpa = $this->cluster->fromYamlFile(__DIR__.'/yaml/hpa.yaml');

        $this->assertEquals('autoscaling/v2', $hpa->getApiVersion());
        $this->assertEquals('mysql-hpa', $hpa->getName());
        $this->assertEquals(['tier' => 'backend'], $hpa->getLabels());
        $this->assertEquals([$resourceMetric->toArray()], $hpa->getMetrics());
        $this->assertEquals(1, $hpa->getMinReplicas());
        $this->assertEquals(10, $hpa->getMaxReplicas());
    }

    public function test_horizontal_pod_autoscaler_api_interaction(): void
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

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $hpa = $this->cluster->horizontalPodAutoscaler()
            ->setName('mysql-hpa')
            ->setLabels(['tier' => 'backend'])
            ->setResource($dep)
            ->addMetrics([$resourceMetric])
            ->min(1)
            ->max(10);

        $this->assertFalse($hpa->isSynced());
        $this->assertFalse($hpa->exists());

        $dep = $dep->createOrUpdate();
        $hpa = $hpa->createOrUpdate();

        $this->assertTrue($hpa->isSynced());
        $this->assertTrue($hpa->exists());

        $this->assertInstanceOf(K8sDeployment::class, $dep);
        $this->assertInstanceOf(K8sHorizontalPodAutoscaler::class, $hpa);

        $this->assertEquals('autoscaling/v2', $hpa->getApiVersion());
        $this->assertEquals('mysql-hpa', $hpa->getName());
        $this->assertEquals(['tier' => 'backend'], $hpa->getLabels());
        $this->assertEquals([$resourceMetric->toArray()], $hpa->getMetrics());
        $this->assertEquals(1, $hpa->getMinReplicas());
        $this->assertEquals(10, $hpa->getMaxReplicas());

        while (! $dep->allPodsAreRunning()) {
            dump(sprintf('Waiting for pods of %s to be up and running...', $dep->getName()));
            sleep(1);
        }

        while ($hpa->getCurrentReplicasCount() < 1) {
            $hpa->refresh();
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to read the current replicas...', $hpa->getName()));
            sleep(1);
        }

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

        $this->assertEquals(1, $hpa->getCurrentReplicasCount());
        $this->assertEquals(0, $hpa->getDesiredReplicasCount());
        $this->assertTrue(is_array($hpa->getConditions()));
    }

    public function runGetAllTests(): void
    {
        $allHorizontalPodAutoscalers = $this->cluster->getAllHorizontalPodAutoscalers();

        $this->assertInstanceOf(ResourcesList::class, $allHorizontalPodAutoscalers);

        foreach ($allHorizontalPodAutoscalers as $allHorizontalPodAutoscaler) {
            $this->assertInstanceOf(K8sHorizontalPodAutoscaler::class, $allHorizontalPodAutoscaler);

            $this->assertNotNull($allHorizontalPodAutoscaler->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sHorizontalPodAutoscaler = $this->cluster->getHorizontalPodAutoscalerByName('mysql-hpa');

        $this->assertInstanceOf(K8sHorizontalPodAutoscaler::class, $k8sHorizontalPodAutoscaler);

        $this->assertTrue($k8sHorizontalPodAutoscaler->isSynced());

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $this->assertEquals('autoscaling/v2', $k8sHorizontalPodAutoscaler->getApiVersion());
        $this->assertEquals('mysql-hpa', $k8sHorizontalPodAutoscaler->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sHorizontalPodAutoscaler->getLabels());
        $this->assertEquals([$resourceMetric->toArray()], $k8sHorizontalPodAutoscaler->getMetrics());
        $this->assertEquals(1, $k8sHorizontalPodAutoscaler->getMinReplicas());
        $this->assertEquals(10, $k8sHorizontalPodAutoscaler->getMaxReplicas());
    }

    public function runUpdateTests(): void
    {
        $k8sHorizontalPodAutoscaler = $this->cluster->getHorizontalPodAutoscalerByName('mysql-hpa');

        $this->assertTrue($k8sHorizontalPodAutoscaler->isSynced());

        $k8sHorizontalPodAutoscaler->max(6);

        $k8sHorizontalPodAutoscaler->createOrUpdate();

        $this->assertTrue($k8sHorizontalPodAutoscaler->isSynced());

        while ($k8sHorizontalPodAutoscaler->getMaxReplicas() < 6) {
            dump(sprintf('Waiting for pod autoscaler %s to get to 6 max replicas...', $k8sHorizontalPodAutoscaler->getName()));
            sleep(1);
            $k8sHorizontalPodAutoscaler->refresh();
        }

        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $this->assertEquals('autoscaling/v2', $k8sHorizontalPodAutoscaler->getApiVersion());
        $this->assertEquals('mysql-hpa', $k8sHorizontalPodAutoscaler->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sHorizontalPodAutoscaler->getLabels());
        $this->assertEquals([$resourceMetric->toArray()], $k8sHorizontalPodAutoscaler->getMetrics());
        $this->assertEquals(1, $k8sHorizontalPodAutoscaler->getMinReplicas());
        $this->assertEquals(6, $k8sHorizontalPodAutoscaler->getMaxReplicas());
    }

    public function runDeletionTests(): void
    {
        $k8sHorizontalPodAutoscaler = $this->cluster->getHorizontalPodAutoscalerByName('mysql-hpa');

        $this->assertTrue($k8sHorizontalPodAutoscaler->delete());

        while ($k8sHorizontalPodAutoscaler->exists()) {
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to be deleted...', $k8sHorizontalPodAutoscaler->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getHorizontalPodAutoscalerByName('mysql-hpa');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->horizontalPodAutoscaler()->watchAll(function ($type, $hpa) {
            if ($hpa->getName() === 'mysql-hpa') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->horizontalPodAutoscaler()->watchByName('mysql-hpa', fn($type, $hpa): bool => $hpa->getName() === 'mysql-hpa', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
