<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPersistentVolume;
use RenokiCo\PhpK8s\ResourcesList;

class PersistentVolumeTest extends TestCase
{
    public function test_persistent_volume_build(): void
    {
        $sc = $this->cluster->storageClass()
            ->setName('sc1')
            ->setProvisioner('csi.aws.amazon.com')
            ->setParameters(['type' => 'sc1'])
            ->setMountOptions(['debug']);

        $k8sPersistentVolume = $this->cluster->persistentVolume()
            ->setName('app')
            ->setLabels(['tier' => 'backend'])
            ->setSource('awsElasticBlockStore', ['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'])
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setMountOptions(['debug'])
            ->setStorageClass($sc);

        $this->assertEquals('v1', $k8sPersistentVolume->getApiVersion());
        $this->assertEquals('app', $k8sPersistentVolume->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolume->getLabels());
        $this->assertEquals(['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'], $k8sPersistentVolume->getSpec('awsElasticBlockStore'));
        $this->assertEquals('1Gi', $k8sPersistentVolume->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolume->getAccessModes());
        $this->assertEquals(['debug'], $k8sPersistentVolume->getMountOptions());
        $this->assertEquals('sc1', $k8sPersistentVolume->getStorageClass());
    }

    public function test_persistent_volume_from_yaml(): void
    {
        $pv = $this->cluster->fromYamlFile(__DIR__.'/yaml/persistentvolume.yaml');

        $this->assertEquals('v1', $pv->getApiVersion());
        $this->assertEquals('app', $pv->getName());
        $this->assertEquals(['tier' => 'backend'], $pv->getLabels());
        $this->assertEquals(['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'], $pv->getSpec('awsElasticBlockStore'));
        $this->assertEquals('1Gi', $pv->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $pv->getAccessModes());
        $this->assertEquals(['debug'], $pv->getMountOptions());
        $this->assertEquals('sc1', $pv->getStorageClass());
    }

    public function test_persistent_volume_api_interaction(): void
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
        $sc = $this->cluster->storageClass()
            ->setName('sc1')
            ->setProvisioner('csi.aws.amazon.com')
            ->setParameters(['type' => 'sc1'])
            ->setMountOptions(['debug']);

        $pv = $this->cluster->persistentVolume()
            ->setName('app')
            ->setLabels(['tier' => 'backend'])
            ->setSource('awsElasticBlockStore', ['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'])
            ->setCapacity(1, 'Gi')
            ->setAccessModes(['ReadWriteOnce'])
            ->setMountOptions(['debug'])
            ->setStorageClass($sc);

        $this->assertFalse($pv->isSynced());
        $this->assertFalse($pv->exists());

        $pv = $pv->createOrUpdate();

        $this->assertTrue($pv->isSynced());
        $this->assertTrue($pv->exists());

        $this->assertInstanceOf(K8sPersistentVolume::class, $pv);

        $this->assertEquals('v1', $pv->getApiVersion());
        $this->assertEquals('app', $pv->getName());
        $this->assertEquals(['tier' => 'backend'], $pv->getLabels());
        $this->assertEquals(['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'], $pv->getSpec('awsElasticBlockStore'));
        $this->assertEquals('1Gi', $pv->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $pv->getAccessModes());
        $this->assertEquals(['debug'], $pv->getMountOptions());
        $this->assertEquals('sc1', $pv->getStorageClass());

        while (! $pv->isAvailable()) {
            dump(sprintf('Waiting for PV %s to be available...', $pv->getName()));
            sleep(1);
            $pv->refresh();
        }

        $this->assertTrue($pv->isAvailable());
        $this->assertFalse($pv->isBound());
    }

    public function runGetAllTests(): void
    {
        $allPersistentVolumes = $this->cluster->getAllPersistentVolumes();

        $this->assertInstanceOf(ResourcesList::class, $allPersistentVolumes);

        foreach ($allPersistentVolumes as $allPersistentVolume) {
            $this->assertInstanceOf(K8sPersistentVolume::class, $allPersistentVolume);

            $this->assertNotNull($allPersistentVolume->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sPersistentVolume = $this->cluster->getPersistentVolumeByName('app');

        $this->assertInstanceOf(K8sPersistentVolume::class, $k8sPersistentVolume);

        $this->assertTrue($k8sPersistentVolume->isSynced());

        $this->assertEquals('v1', $k8sPersistentVolume->getApiVersion());
        $this->assertEquals('app', $k8sPersistentVolume->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolume->getLabels());
        $this->assertEquals(['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'], $k8sPersistentVolume->getSpec('awsElasticBlockStore'));
        $this->assertEquals('1Gi', $k8sPersistentVolume->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolume->getAccessModes());
        $this->assertEquals(['debug'], $k8sPersistentVolume->getMountOptions());
        $this->assertEquals('sc1', $k8sPersistentVolume->getStorageClass());
    }

    public function runUpdateTests(): void
    {
        $k8sPersistentVolume = $this->cluster->getPersistentVolumeByName('app');

        $this->assertTrue($k8sPersistentVolume->isSynced());

        $k8sPersistentVolume->setMountOptions(['debug', 'test']);

        $k8sPersistentVolume->createOrUpdate();

        $this->assertTrue($k8sPersistentVolume->isSynced());

        $this->assertEquals('v1', $k8sPersistentVolume->getApiVersion());
        $this->assertEquals('app', $k8sPersistentVolume->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPersistentVolume->getLabels());
        $this->assertEquals(['fsType' => 'ext4', 'volumeID' => 'vol-xxxxx'], $k8sPersistentVolume->getSpec('awsElasticBlockStore'));
        $this->assertEquals('1Gi', $k8sPersistentVolume->getCapacity());
        $this->assertEquals(['ReadWriteOnce'], $k8sPersistentVolume->getAccessModes());
        $this->assertEquals(['debug', 'test'], $k8sPersistentVolume->getMountOptions());
        $this->assertEquals('sc1', $k8sPersistentVolume->getStorageClass());
    }

    public function runDeletionTests(): void
    {
        $k8sPersistentVolume = $this->cluster->getPersistentVolumeByName('app');

        $this->assertTrue($k8sPersistentVolume->delete());

        while ($k8sPersistentVolume->exists()) {
            dump(sprintf('Awaiting for PV %s to be deleted...', $k8sPersistentVolume->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getPersistentVolumeByName('app');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->persistentVolume()->watchAll(function ($type, $pv) {
            if ($pv->getName() === 'app') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->persistentVolume()->watchByName('app', fn($type, $pv): bool => $pv->getName() === 'app', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
