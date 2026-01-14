<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sConfigMap;
use RenokiCo\PhpK8s\ResourcesList;

class ConfigMapTest extends TestCase
{
    public function test_config_map_build(): void
    {
        $k8sConfigMap = $this->cluster->configmap()
            ->setName('settings')
            ->setLabels(['tier' => 'backend'])
            ->setData(['somekey' => 'somevalue'])
            ->addData('key2', 'val2')
            ->removeData('somekey')
            ->immutable();

        $this->assertEquals('v1', $k8sConfigMap->getApiVersion());
        $this->assertEquals('settings', $k8sConfigMap->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sConfigMap->getLabels());
        $this->assertEquals(['key2' => 'val2'], $k8sConfigMap->getData());
        $this->assertTrue($k8sConfigMap->isImmutable());
    }

    public function test_config_map_from_yaml(): void
    {
        $cm = $this->cluster->fromYamlFile(__DIR__.'/yaml/configmap.yaml');

        $this->assertEquals('v1', $cm->getApiVersion());
        $this->assertEquals('settings', $cm->getName());
        $this->assertEquals(['tier' => 'backend'], $cm->getLabels());
        $this->assertEquals(['key2' => 'val2'], $cm->getData());
        $this->assertTrue($cm->isImmutable());
    }

    public function test_config_map_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runDeletionTests();
    }

    public function test_immutability(): void
    {
        $k8sConfigMap = $this->cluster->configmap()
            ->setName('settings')
            ->setLabels(['tier' => 'backend'])
            ->setData(['somekey' => 'somevalue'])
            ->addData('key2', 'val2')
            ->removeData('somekey')
            ->immutable();

        $k8sConfigMap->createOrUpdate();

        $k8sConfigMap->refresh();

        $this->assertTrue($k8sConfigMap->isImmutable());

        $k8sConfigMap->delete();
    }

    public function runCreationTests(): void
    {
        $cm = $this->cluster->configmap()
            ->setName('settings')
            ->setLabels(['tier' => 'backend'])
            ->setData(['somekey' => 'somevalue'])
            ->addData('key2', 'val2')
            ->removeData('somekey');

        $this->assertFalse($cm->isSynced());
        $this->assertFalse($cm->exists());

        $cm = $cm->createOrUpdate();

        $this->assertTrue($cm->isSynced());
        $this->assertTrue($cm->exists());

        $this->assertInstanceOf(K8sConfigMap::class, $cm);

        $this->assertEquals('v1', $cm->getApiVersion());
        $this->assertEquals('settings', $cm->getName());
        $this->assertEquals(['tier' => 'backend'], $cm->getLabels());
        $this->assertEquals(['key2' => 'val2'], $cm->getData());
        $this->assertEquals('val2', $cm->getData('key2'));
    }

    public function runGetAllTests(): void
    {
        $allConfigmaps = $this->cluster->getAllConfigmaps();

        $this->assertInstanceOf(ResourcesList::class, $allConfigmaps);

        foreach ($allConfigmaps as $allConfigmap) {
            $this->assertInstanceOf(K8sConfigMap::class, $allConfigmap);

            $this->assertNotNull($allConfigmap->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sConfigMap = $this->cluster->getConfigmapByName('settings');

        $this->assertInstanceOf(K8sConfigMap::class, $k8sConfigMap);

        $this->assertTrue($k8sConfigMap->isSynced());

        $this->assertEquals('v1', $k8sConfigMap->getApiVersion());
        $this->assertEquals('settings', $k8sConfigMap->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sConfigMap->getLabels());
        $this->assertEquals(['key2' => 'val2'], $k8sConfigMap->getData());
        $this->assertEquals('val2', $k8sConfigMap->getData('key2'));
    }

    public function runUpdateTests(): void
    {
        $k8sConfigMap = $this->cluster->getConfigmapByName('settings');

        $this->assertTrue($k8sConfigMap->isSynced());

        $k8sConfigMap->removeData('key2')
            ->addData('newkey', 'newval');

        $k8sConfigMap->createOrUpdate();

        $this->assertTrue($k8sConfigMap->isSynced());

        $this->assertEquals('v1', $k8sConfigMap->getApiVersion());
        $this->assertEquals('settings', $k8sConfigMap->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sConfigMap->getLabels());
        $this->assertEquals(['newkey' => 'newval'], $k8sConfigMap->getData());
        $this->assertEquals('newval', $k8sConfigMap->getData('newkey'));
    }

    public function runDeletionTests(): void
    {
        $k8sConfigMap = $this->cluster->getConfigmapByName('settings');

        $this->assertTrue($k8sConfigMap->delete());

        while ($k8sConfigMap->exists()) {
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getConfigmapByName('settings');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->configmap()->watchAll(function ($type, $configmap) {
            if ($configmap->getName() === 'settings') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->configmap()->watchByName('settings', fn($type, $configmap): bool => $configmap->getName() === 'settings', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
