<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sServiceAccount;
use RenokiCo\PhpK8s\ResourcesList;

class ServiceAccountTest extends TestCase
{
    public function test_service_account_build(): void
    {
        $k8sSecret = $this->cluster->secret()
            ->setName('passwords')
            ->addData('postgres', 'postgres');

        $k8sServiceAccount = $this->cluster->serviceAccount()
            ->setName('user1')
            ->setLabels(['tier' => 'backend'])
            ->addSecrets([$k8sSecret])
            ->setSecrets([$k8sSecret])
            ->addPulledSecrets(['postgres']);

        $this->assertEquals('v1', $k8sServiceAccount->getApiVersion());
        $this->assertEquals('user1', $k8sServiceAccount->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sServiceAccount->getLabels());
        $this->assertEquals([['name' => $k8sSecret->getName()]], $k8sServiceAccount->getSecrets());
        $this->assertEquals([['name' => 'postgres']], $k8sServiceAccount->getImagePullSecrets());
    }

    public function test_service_account_from_yaml(): void
    {
        $k8sSecret = $this->cluster->secret()
            ->setName('passwords')
            ->setLabels(['tier' => 'backend'])
            ->addData('postgres', 'postgres');

        $sa = $this->cluster->fromYamlFile(__DIR__.'/yaml/serviceaccount.yaml');

        $this->assertEquals('v1', $sa->getApiVersion());
        $this->assertEquals('user1', $sa->getName());
        $this->assertEquals(['tier' => 'backend'], $sa->getLabels());
        $this->assertEquals([['name' => $k8sSecret->getName()]], $sa->getSecrets());
        $this->assertEquals([['name' => 'postgres']], $sa->getImagePullSecrets());
    }

    public function test_service_account_api_interaction(): void
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
        $secret = $this->cluster->secret()
            ->setName('passwords')
            ->addData('postgres', 'postgres');

        $sa = $this->cluster->serviceAccount()
            ->setName('user1')
            ->setLabels(['tier' => 'backend'])
            ->addSecrets([$secret])
            ->setSecrets([$secret])
            ->addPulledSecrets(['postgres']);

        $this->assertFalse($sa->isSynced());
        $this->assertFalse($sa->exists());

        $sa = $sa->createOrUpdate();
        $secret = $secret->createOrUpdate();

        $this->assertTrue($sa->isSynced());
        $this->assertTrue($sa->exists());

        $this->assertInstanceOf(K8sServiceAccount::class, $sa);

        $this->assertEquals('v1', $sa->getApiVersion());
        $this->assertEquals('user1', $sa->getName());
        $this->assertEquals(['tier' => 'backend'], $sa->getLabels());
        $this->assertEquals([['name' => $secret->getName()]], $sa->getSecrets());
        $this->assertEquals([['name' => 'postgres']], $sa->getImagePullSecrets());
    }

    public function runGetAllTests(): void
    {
        $allServiceAccounts = $this->cluster->getAllServiceAccounts();

        $this->assertInstanceOf(ResourcesList::class, $allServiceAccounts);

        foreach ($allServiceAccounts as $allServiceAccount) {
            $this->assertInstanceOf(K8sServiceAccount::class, $allServiceAccount);

            $this->assertNotNull($allServiceAccount->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sServiceAccount = $this->cluster->getServiceAccountByName('user1');
        $k8sSecret = $this->cluster->getSecretByName('passwords');

        $this->assertInstanceOf(K8sServiceAccount::class, $k8sServiceAccount);

        $this->assertTrue($k8sServiceAccount->isSynced());

        $this->assertEquals('v1', $k8sServiceAccount->getApiVersion());
        $this->assertEquals('user1', $k8sServiceAccount->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sServiceAccount->getLabels());
        $this->assertEquals(['name' => $k8sSecret->getName()], $k8sServiceAccount->getSecrets()[0]);
        $this->assertEquals([['name' => 'postgres']], $k8sServiceAccount->getImagePullSecrets());
    }

    public function runUpdateTests(): void
    {
        $k8sServiceAccount = $this->cluster->getServiceAccountByName('user1');
        $k8sSecret = $this->cluster->getSecretByName('passwords');

        $this->assertTrue($k8sServiceAccount->isSynced());

        $k8sServiceAccount->addPulledSecrets(['postgres2']);

        $k8sServiceAccount->createOrUpdate();

        $this->assertTrue($k8sServiceAccount->isSynced());

        $this->assertEquals('v1', $k8sServiceAccount->getApiVersion());
        $this->assertEquals('user1', $k8sServiceAccount->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sServiceAccount->getLabels());
        $this->assertEquals(['name' => $k8sSecret->getName()], $k8sServiceAccount->getSecrets()[0]);
        $this->assertEquals([['name' => 'postgres'], ['name' => 'postgres2']], $k8sServiceAccount->getImagePullSecrets());
    }

    public function runDeletionTests(): void
    {
        $k8sServiceAccount = $this->cluster->getServiceAccountByName('user1');

        $this->assertTrue($k8sServiceAccount->delete());

        while ($k8sServiceAccount->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getServiceAccountByName('user1');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->serviceAccount()->watchAll(function ($type, $serviceAccount) {
            if ($serviceAccount->getName() === 'user1') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->serviceAccount()->watchByName('user1', fn($type, $serviceAccount): bool => $serviceAccount->getName() === 'user1', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
