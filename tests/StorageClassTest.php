<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sStorageClass;
use RenokiCo\PhpK8s\ResourcesList;

class StorageClassTest extends TestCase
{
    public function test_storage_class_build(): void
    {
        $sc = $this->cluster->storageClass()
            ->setName('io1')
            ->setLabels(['tier' => 'backend'])
            ->setProvisioner('csi.aws.amazon.com')
            ->setParameters(['type' => 'io1', 'iopsPerGB' => 10])
            ->setMountOptions(['debug']);

        $this->assertEquals('storage.k8s.io/v1', $sc->getApiVersion());
        $this->assertEquals('io1', $sc->getName());
        $this->assertEquals(['tier' => 'backend'], $sc->getLabels());
        $this->assertEquals('csi.aws.amazon.com', $sc->getProvisioner());
        $this->assertEquals(['type' => 'io1', 'iopsPerGB' => 10], $sc->getParameters());
        $this->assertEquals(['debug'], $sc->getMountOptions());
    }

    public function test_storage_class_from_yaml(): void
    {
        $sc = $this->cluster->fromYamlFile(__DIR__.'/yaml/storageclass.yaml');

        $this->assertEquals('storage.k8s.io/v1', $sc->getApiVersion());
        $this->assertEquals('io1', $sc->getName());
        $this->assertEquals(['tier' => 'backend'], $sc->getLabels());
        $this->assertEquals('csi.aws.amazon.com', $sc->getProvisioner());
        $this->assertEquals(['type' => 'io1', 'iopsPerGB' => 10], $sc->getParameters());
        $this->assertEquals(['debug'], $sc->getMountOptions());
    }

    public function test_storage_class_api_interaction(): void
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
            ->setName('io1')
            ->setLabels(['tier' => 'backend'])
            ->setProvisioner('csi.aws.amazon.com')
            ->setParameters(['type' => 'io1', 'iopsPerGB' => '10'])
            ->setMountOptions(['debug']);

        $this->assertFalse($sc->isSynced());
        $this->assertFalse($sc->exists());

        $sc = $sc->createOrUpdate();

        $this->assertTrue($sc->isSynced());
        $this->assertTrue($sc->exists());

        $this->assertInstanceOf(K8sStorageClass::class, $sc);

        $this->assertEquals('storage.k8s.io/v1', $sc->getApiVersion());
        $this->assertEquals('io1', $sc->getName());
        $this->assertEquals(['tier' => 'backend'], $sc->getLabels());
        $this->assertEquals('csi.aws.amazon.com', $sc->getProvisioner());
        $this->assertEquals(['type' => 'io1', 'iopsPerGB' => 10], $sc->getParameters());
        $this->assertEquals(['debug'], $sc->getMountOptions());
    }

    public function runGetAllTests(): void
    {
        $allStorageClasses = $this->cluster->getAllStorageClasses();

        $this->assertInstanceOf(ResourcesList::class, $allStorageClasses);

        foreach ($allStorageClasses as $allStorageClass) {
            $this->assertInstanceOf(K8sStorageClass::class, $allStorageClass);

            $this->assertNotNull($allStorageClass->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sStorageClass = $this->cluster->getStorageClassByName('io1');

        $this->assertInstanceOf(K8sStorageClass::class, $k8sStorageClass);

        $this->assertTrue($k8sStorageClass->isSynced());

        $this->assertEquals('storage.k8s.io/v1', $k8sStorageClass->getApiVersion());
        $this->assertEquals('io1', $k8sStorageClass->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sStorageClass->getLabels());
        $this->assertEquals('csi.aws.amazon.com', $k8sStorageClass->getProvisioner());
        $this->assertEquals(['type' => 'io1', 'iopsPerGB' => 10], $k8sStorageClass->getParameters());
        $this->assertEquals(['debug'], $k8sStorageClass->getMountOptions());
    }

    public function runUpdateTests(): void
    {
        $k8sStorageClass = $this->cluster->getStorageClassByName('io1');

        $this->assertTrue($k8sStorageClass->isSynced());

        $k8sStorageClass->setAttribute('mountOptions', ['debug']);

        $k8sStorageClass->createOrUpdate();

        $this->assertTrue($k8sStorageClass->isSynced());

        $this->assertEquals('storage.k8s.io/v1', $k8sStorageClass->getApiVersion());
        $this->assertEquals('io1', $k8sStorageClass->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sStorageClass->getLabels());
        $this->assertEquals(['debug'], $k8sStorageClass->getAttribute('mountOptions'));
        $this->assertEquals(['type' => 'io1', 'iopsPerGB' => '10'], $k8sStorageClass->getParameters());
        $this->assertEquals(['debug'], $k8sStorageClass->getMountOptions());
    }

    public function runDeletionTests(): void
    {
        $k8sStorageClass = $this->cluster->getStorageClassByName('io1');

        $this->assertTrue($k8sStorageClass->delete());

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getStorageClassByName('io1');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->storageClass()->watchAll(function ($type, $sc) {
            if ($sc->getName() === 'io1') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->storageClass()->watchByName('io1', fn($type, $sc): bool => $sc->getName() === 'io1', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
