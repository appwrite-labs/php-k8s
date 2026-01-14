<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolumeClaim;
use RenokiCo\PhpK8s\ResourcesList;

class PersistentVolumeClaimTest extends TestCase
{
    public function test_persistent_volume_claim_build(): void
    {
        $k8sStorageClass = $this->cluster->getStorageClassByName('standard');

        $k8sPersistentVolumeClaim = $this->cluster->persistentVolumeClaim()
            ->setName('app-pvc')
            ->setLabels(['tier' => 'backend'])
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass($k8sStorageClass);

        $this->assertEquals('v1', $k8sPersistentVolumeClaim->getApiVersion());
        $this->assertEquals('app-pvc', $k8sPersistentVolumeClaim->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolumeClaim->getLabels());
        $this->assertEquals('1Gi', $k8sPersistentVolumeClaim->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolumeClaim->getAccessModes());
        $this->assertEquals('standard', $k8sPersistentVolumeClaim->getStorageClass());
    }

    public function test_persistent_volume_claim_from_yaml(): void
    {
        $pvc = $this->cluster->fromYamlFile(__DIR__.'/yaml/persistentvolumeclaim.yaml');

        $this->assertEquals('v1', $pvc->getApiVersion());
        $this->assertEquals('app-pvc', $pvc->getName());
        $this->assertEquals(['tier' => 'backend'], $pvc->getLabels());
        $this->assertEquals('1Gi', $pvc->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $pvc->getAccessModes());
        $this->assertEquals('standard', $pvc->getStorageClass());
    }

    public function test_persistent_volume_claim_api_interaction(): void
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
        $k8sStorageClass = $this->cluster->getStorageClassByName('standard');

        $pvc = $this->cluster->persistentVolumeClaim()
            ->setName('app-pvc')
            ->setLabels(['tier' => 'backend'])
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setStorageClass($k8sStorageClass);

        $this->assertFalse($pvc->isSynced());
        $this->assertFalse($pvc->exists());

        $pvc = $pvc->createOrUpdate();

        $this->assertTrue($pvc->isSynced());
        $this->assertTrue($pvc->exists());

        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $pvc);

        $this->assertEquals('v1', $pvc->getApiVersion());
        $this->assertEquals('app-pvc', $pvc->getName());
        $this->assertEquals(['tier' => 'backend'], $pvc->getLabels());
        $this->assertEquals('1Gi', $pvc->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $pvc->getAccessModes());
        $this->assertEquals('standard', $pvc->getStorageClass());

        if ($k8sStorageClass->getVolumeBindingMode() == 'Immediate') {
            while (! $pvc->isBound()) {
                dump(sprintf('Waiting for PVC %s to be bound...', $pvc->getName()));
                sleep(1);
                $pvc->refresh();
            }

            $this->assertFalse($pvc->isAvailable());
            $this->assertTrue($pvc->isBound());
        }
    }

    public function runGetAllTests(): void
    {
        $allPersistentVolumeClaims = $this->cluster->getAllPersistentVolumeClaims();

        $this->assertInstanceOf(ResourcesList::class, $allPersistentVolumeClaims);

        foreach ($allPersistentVolumeClaims as $allPersistentVolumeClaim) {
            $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $allPersistentVolumeClaim);

            $this->assertNotNull($allPersistentVolumeClaim->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sPersistentVolumeClaim = $this->cluster->getPersistentVolumeClaimByName('app-pvc');

        $this->assertInstanceOf(K8sPersistentVolumeClaim::class, $k8sPersistentVolumeClaim);

        $this->assertTrue($k8sPersistentVolumeClaim->isSynced());

        $this->assertEquals('v1', $k8sPersistentVolumeClaim->getApiVersion());
        $this->assertEquals('app-pvc', $k8sPersistentVolumeClaim->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolumeClaim->getLabels());
        $this->assertEquals('1Gi', $k8sPersistentVolumeClaim->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolumeClaim->getAccessModes());
        $this->assertEquals('standard', $k8sPersistentVolumeClaim->getStorageClass());
    }

    public function runUpdateTests(): void
    {
        $k8sPersistentVolumeClaim = $this->cluster->getPersistentVolumeClaimByName('app-pvc');

        $this->assertTrue($k8sPersistentVolumeClaim->isSynced());

        $k8sPersistentVolumeClaim->createOrUpdate();

        $this->assertTrue($k8sPersistentVolumeClaim->isSynced());

        $this->assertEquals('v1', $k8sPersistentVolumeClaim->getApiVersion());
        $this->assertEquals('app-pvc', $k8sPersistentVolumeClaim->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolumeClaim->getLabels());
        $this->assertEquals('1Gi', $k8sPersistentVolumeClaim->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolumeClaim->getAccessModes());
        $this->assertEquals('standard', $k8sPersistentVolumeClaim->getStorageClass());
    }

    public function runDeletionTests(): void
    {
        $k8sPersistentVolumeClaim = $this->cluster->getPersistentVolumeClaimByName('app-pvc');

        $this->assertTrue($k8sPersistentVolumeClaim->delete());

        while ($k8sPersistentVolumeClaim->exists()) {
            dump(sprintf('Awaiting for PVC %s to be deleted...', $k8sPersistentVolumeClaim->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getPersistentVolumeClaimByName('app-pvc');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->persistentVolumeClaim()->watchAll(function ($type, $pvc) {
            if ($pvc->getName() === 'app-pvc') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->persistentVolumeClaim()->watchByName('app-pvc', fn($type, $pvc): bool => $pvc->getName() === 'app-pvc', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
