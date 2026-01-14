<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sPodDisruptionBudget;
use RenokiCo\PhpK8s\ResourcesList;

class PodDisruptionBudgetTest extends TestCase
{
    public function test_pod_disruption_budget_build(): void
    {
        $k8sPodDisruptionBudget = $this->cluster->podDisruptionBudget()
            ->setName('mysql-pdb')
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setMinAvailable(1)
            ->setMaxUnavailable('25%');

        $this->assertEquals('policy/v1', $k8sPodDisruptionBudget->getApiVersion());
        $this->assertEquals('mysql-pdb', $k8sPodDisruptionBudget->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $k8sPodDisruptionBudget->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $k8sPodDisruptionBudget->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sPodDisruptionBudget->getAnnotations());
        $this->assertEquals('25%', $k8sPodDisruptionBudget->getMaxUnavailable());
        $this->assertEquals(null, $k8sPodDisruptionBudget->getMinAvailable());
    }

    public function test_pod_disruption_budget_from_yaml(): void
    {
        [$pdb1, $pdb2] = $this->cluster->fromYamlFile(__DIR__.'/yaml/pdb.yaml');

        $this->assertEquals('policy/v1', $pdb1->getApiVersion());
        $this->assertEquals('mysql-pdb', $pdb1->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $pdb1->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $pdb1->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pdb1->getAnnotations());
        $this->assertEquals('25%', $pdb1->getMaxUnavailable());
        $this->assertEquals(null, $pdb1->getMinAvailable());

        $this->assertEquals('policy/v1', $pdb2->getApiVersion());
        $this->assertEquals('mysql-pdb', $pdb2->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $pdb2->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $pdb2->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pdb2->getAnnotations());
        $this->assertEquals(null, $pdb2->getMaxUnavailable());
        $this->assertEquals('25%', $pdb2->getMinAvailable());
    }

    public function test_pod_disruption_budget_api_interaction(): void
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

        $pdb = $this->cluster->podDisruptionBudget()
            ->setName('mysql-pdb')
            ->setSelectors(['matchLabels' => ['tier' => 'backend']])
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->setMinAvailable(1)
            ->setMaxUnavailable('25%');

        $this->assertFalse($pdb->isSynced());
        $this->assertFalse($pdb->exists());

        $dep = $dep->createOrUpdate();
        $pdb = $pdb->createOrUpdate();

        $this->assertTrue($pdb->isSynced());
        $this->assertTrue($pdb->exists());

        $this->assertInstanceOf(K8sDeployment::class, $dep);
        $this->assertInstanceOf(K8sPodDisruptionBudget::class, $pdb);

        $this->assertEquals('policy/v1', $pdb->getApiVersion());
        $this->assertEquals('mysql-pdb', $pdb->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $pdb->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $pdb->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pdb->getAnnotations());
        $this->assertEquals('25%', $pdb->getMaxUnavailable());
        $this->assertEquals(null, $pdb->getMinAvailable());

        while (! $dep->allPodsAreRunning()) {
            dump(sprintf('Waiting for pods of %s to be up and running...', $dep->getName()));
            sleep(1);
        }
    }

    public function runGetAllTests(): void
    {
        $allPodDisruptionBudgets = $this->cluster->getAllPodDisruptionBudgets();

        $this->assertInstanceOf(ResourcesList::class, $allPodDisruptionBudgets);

        foreach ($allPodDisruptionBudgets as $allPodDisruptionBudget) {
            $this->assertInstanceOf(K8sPodDisruptionBudget::class, $allPodDisruptionBudget);

            $this->assertNotNull($allPodDisruptionBudget->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sPodDisruptionBudget = $this->cluster->getPodDisruptionBudgetByName('mysql-pdb');

        $this->assertInstanceOf(K8sPodDisruptionBudget::class, $k8sPodDisruptionBudget);

        $this->assertTrue($k8sPodDisruptionBudget->isSynced());

        $this->assertEquals('policy/v1', $k8sPodDisruptionBudget->getApiVersion());
        $this->assertEquals('mysql-pdb', $k8sPodDisruptionBudget->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $k8sPodDisruptionBudget->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $k8sPodDisruptionBudget->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sPodDisruptionBudget->getAnnotations());
        $this->assertEquals('25%', $k8sPodDisruptionBudget->getMaxUnavailable());
        $this->assertEquals(null, $k8sPodDisruptionBudget->getMinAvailable());
    }

    public function runUpdateTests(): void
    {
        $backoff = 0;
        do {
            try {
                $pdb = $this->cluster->getPodDisruptionBudgetByName('mysql-pdb')->setMinAvailable('25%')->createOrUpdate();
            } catch (KubernetesAPIException $e) {
                if ($e->getCode() == 409) {
                    sleep(2 * $backoff);
                    $backoff++;
                } else {
                    throw $e;
                }

                if ($backoff > 3) {
                    break;
                }
            }
        } while (! isset($pdb));

        $this->assertTrue($pdb->isSynced());

        $this->assertEquals('policy/v1', $pdb->getApiVersion());
        $this->assertEquals('mysql-pdb', $pdb->getName());
        $this->assertEquals(['matchLabels' => ['tier' => 'backend']], $pdb->getSelectors());
        $this->assertEquals(['tier' => 'backend'], $pdb->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pdb->getAnnotations());
        $this->assertEquals(null, $pdb->getMaxUnavailable());
        $this->assertEquals('25%', $pdb->getMinAvailable());
    }

    public function runDeletionTests(): void
    {
        $k8sPodDisruptionBudget = $this->cluster->getPodDisruptionBudgetByName('mysql-pdb');

        $this->assertTrue($k8sPodDisruptionBudget->delete());

        while ($k8sPodDisruptionBudget->exists()) {
            dump(sprintf('Awaiting for pod disruption budget %s to be deleted...', $k8sPodDisruptionBudget->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getPodDisruptionBudgetByName('mysql-pdb');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->podDisruptionBudget()->watchAll(function ($type, $pdb) {
            if ($pdb->getName() === 'mysql-pdb') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->podDisruptionBudget()->watchByName('mysql-pdb', fn($type, $pdb): bool => $pdb->getName() === 'mysql-pdb', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
