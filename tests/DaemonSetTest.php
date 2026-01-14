<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sDaemonSet;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class DaemonSetTest extends TestCase
{
    public function test_daemon_set_build(): void
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

        $k8sDaemonSet = $this->cluster->daemonSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setUpdateStrategy('RollingUpdate')
            ->setMinReadySeconds(0)
            ->setTemplate($k8sPod);

        $this->assertEquals('apps/v1', $k8sDaemonSet->getApiVersion());
        $this->assertEquals('mysql', $k8sDaemonSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDaemonSet->getLabels());
        $this->assertEquals(0, $k8sDaemonSet->getMinReadySeconds());
        $this->assertEquals($k8sPod->getName(), $k8sDaemonSet->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $k8sDaemonSet->getTemplate());
    }

    public function test_daemon_set_from_yaml(): void
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

        $ds = $this->cluster->fromYamlFile(__DIR__.'/yaml/daemonset.yaml');

        $this->assertEquals('apps/v1', $ds->getApiVersion());
        $this->assertEquals('mysql', $ds->getName());
        $this->assertEquals(['tier' => 'backend'], $ds->getLabels());
        $this->assertEquals($k8sPod->getName(), $ds->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $ds->getTemplate());
    }

    public function test_daemon_set_api_interaction(): void
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
            ->setLabels(['tier' => 'backend', 'daemonset-name' => 'mysql'])
            ->setContainers([$mysql]);

        $ds = $this->cluster->daemonSet()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setUpdateStrategy('RollingUpdate')
            ->setMinReadySeconds(0)
            ->setTemplate($k8sPod);

        $this->assertFalse($ds->isSynced());
        $this->assertFalse($ds->exists());

        $ds = $ds->createOrUpdate();

        $this->assertTrue($ds->isSynced());
        $this->assertTrue($ds->exists());

        $this->assertInstanceOf(K8sDaemonSet::class, $ds);

        $this->assertEquals('apps/v1', $ds->getApiVersion());
        $this->assertEquals('mysql', $ds->getName());
        $this->assertEquals(['tier' => 'backend'], $ds->getLabels());
        $this->assertEquals(0, $ds->getMinReadySeconds());
        $this->assertEquals($k8sPod->getName(), $ds->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $ds->getTemplate());

        while (! $ds->allPodsAreRunning()) {
            dump(sprintf('Waiting for pods of %s to be up and running...', $ds->getName()));
            sleep(1);
        }

        K8sDaemonSet::selectPods(function ($ds): array {
            $this->assertInstanceOf(K8sDaemonSet::class, $ds);

            return ['tier' => 'backend'];
        });

        $pods = $ds->getPods();
        $this->assertTrue($pods->count() > 0);

        K8sDaemonSet::resetPodsSelector();

        $pods = $ds->getPods();
        $this->assertTrue($pods->count() > 0);

        foreach ($pods as $pod) {
            $this->assertInstanceOf(K8sPod::class, $pod);
        }

        $ds->refresh();

        while ($ds->getReadyReplicasCount() === 0) {
            dump(sprintf('Waiting for pods of %s to have ready replicas...', $ds->getName()));
            sleep(1);
            $ds->refresh();
        }

        while ($ds->getNodesCount() === 0) {
            dump(sprintf('Waiting for pods of %s to get detected...', $ds->getName()));
            sleep(1);
            $ds->refresh();
        }

        $this->assertEquals(1, $ds->getScheduledCount());
        $this->assertEquals(0, $ds->getMisscheduledCount());
        $this->assertEquals(1, $ds->getNodesCount());
        $this->assertEquals(1, $ds->getDesiredCount());
        $this->assertEquals(1, $ds->getReadyCount());
        $this->assertEquals(0, $ds->getUnavailableClount());

        $this->assertTrue(is_array($ds->getConditions()));
    }

    public function runGetAllTests(): void
    {
        $allDaemonSets = $this->cluster->getAllDaemonSets();

        $this->assertInstanceOf(ResourcesList::class, $allDaemonSets);

        foreach ($allDaemonSets as $allDaemonSet) {
            $this->assertInstanceOf(K8sDaemonSet::class, $allDaemonSet);

            $this->assertNotNull($allDaemonSet->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sDaemonSet = $this->cluster->getDaemonSetByName('mysql');

        $this->assertInstanceOf(K8sDaemonSet::class, $k8sDaemonSet);

        $this->assertTrue($k8sDaemonSet->isSynced());

        $this->assertEquals('apps/v1', $k8sDaemonSet->getApiVersion());
        $this->assertEquals('mysql', $k8sDaemonSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDaemonSet->getLabels());

        $this->assertInstanceOf(K8sPod::class, $k8sDaemonSet->getTemplate());
    }

    public function runUpdateTests(): void
    {
        $k8sDaemonSet = $this->cluster->getDaemonSetByName('mysql');

        $this->assertTrue($k8sDaemonSet->isSynced());

        $k8sDaemonSet->createOrUpdate();

        $this->assertTrue($k8sDaemonSet->isSynced());

        $this->assertEquals('apps/v1', $k8sDaemonSet->getApiVersion());
        $this->assertEquals('mysql', $k8sDaemonSet->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sDaemonSet->getLabels());

        $this->assertInstanceOf(K8sPod::class, $k8sDaemonSet->getTemplate());
    }

    public function runDeletionTests(): void
    {
        $k8sDaemonSet = $this->cluster->getDaemonSetByName('mysql');

        $this->assertTrue($k8sDaemonSet->delete());

        while ($k8sDaemonSet->exists()) {
            dump(sprintf('Awaiting for daemonSet %s to be deleted...', $k8sDaemonSet->getName()));
            sleep(1);
        }

        while ($k8sDaemonSet->getPods()->count() > 0) {
            dump(sprintf("Awaiting for daemonset %s's pods to be deleted...", $k8sDaemonSet->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getDaemonSetByName('mysql');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->daemonSet()->watchAll(function ($type, $ds) {
            if ($ds->getName() === 'mysql') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->daemonSet()->watchByName('mysql', fn($type, $ds): bool => $ds->getName() === 'mysql', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
