<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sSecret;
use RenokiCo\PhpK8s\ResourcesList;

class SecretTest extends TestCase
{
    public function test_secret_build(): void
    {
        $k8sSecret = $this->cluster->secret()
            ->setName('passwords')
            ->setLabels(['tier' => 'backend'])
            ->setData(['root' => 'somevalue'])
            ->addData('postgres', 'postgres')
            ->removeData('root')
            ->immutable();

        $this->assertEquals('v1', $k8sSecret->getApiVersion());
        $this->assertEquals('passwords', $k8sSecret->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sSecret->getLabels());
        $this->assertEquals(['postgres' => base64_encode('postgres')], $k8sSecret->getData(false));
        $this->assertEquals(['postgres' => 'postgres'], $k8sSecret->getData(true));
        $this->assertTrue($k8sSecret->isImmutable());
    }

    public function test_secret_from_yaml(): void
    {
        $secret = $this->cluster->fromYamlFile(__DIR__.'/yaml/secret.yaml');

        $this->assertEquals('v1', $secret->getApiVersion());
        $this->assertEquals('passwords', $secret->getName());
        $this->assertEquals(['tier' => 'backend'], $secret->getLabels());
        $this->assertEquals(['postgres' => base64_encode('postgres')], $secret->getData(false));
        $this->assertEquals(['postgres' => 'postgres'], $secret->getData(true));
        $this->assertTrue($secret->isImmutable());
    }

    public function test_secret_api_interaction(): void
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
        $k8sSecret = $this->cluster->secret()
            ->setName('passwords')
            ->setLabels(['tier' => 'backend'])
            ->setData(['root' => 'somevalue'])
            ->addData('postgres', 'postgres')
            ->removeData('root')
            ->immutable();

        $k8sSecret->createOrUpdate();

        $k8sSecret->refresh();

        $this->assertTrue($k8sSecret->isImmutable());

        $k8sSecret->delete();
    }

    public function runCreationTests(): void
    {
        $secret = $this->cluster->secret()
            ->setName('passwords')
            ->setLabels(['tier' => 'backend'])
            ->setData(['root' => 'somevalue'])
            ->addData('postgres', 'postgres')
            ->removeData('root');

        $this->assertFalse($secret->isSynced());
        $this->assertFalse($secret->exists());

        $secret = $secret->createOrUpdate();

        $this->assertTrue($secret->isSynced());
        $this->assertTrue($secret->exists());

        $this->assertInstanceOf(K8sSecret::class, $secret);

        $this->assertEquals('v1', $secret->getApiVersion());
        $this->assertEquals('passwords', $secret->getName());
        $this->assertEquals(['tier' => 'backend'], $secret->getLabels());
        $this->assertEquals(['postgres' => base64_encode('postgres')], $secret->getData(false));
        $this->assertEquals(['postgres' => 'postgres'], $secret->getData(true));
    }

    public function runGetAllTests(): void
    {
        $allSecrets = $this->cluster->getAllSecrets();

        $this->assertInstanceOf(ResourcesList::class, $allSecrets);

        foreach ($allSecrets as $allSecret) {
            $this->assertInstanceOf(K8sSecret::class, $allSecret);

            $this->assertNotNull($allSecret->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sSecret = $this->cluster->getSecretByName('passwords');

        $this->assertInstanceOf(K8sSecret::class, $k8sSecret);

        $this->assertTrue($k8sSecret->isSynced());

        $this->assertEquals('v1', $k8sSecret->getApiVersion());
        $this->assertEquals('passwords', $k8sSecret->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sSecret->getLabels());
        $this->assertEquals(['postgres' => base64_encode('postgres')], $k8sSecret->getData(false));
        $this->assertEquals(['postgres' => 'postgres'], $k8sSecret->getData(true));
    }

    public function runUpdateTests(): void
    {
        $k8sSecret = $this->cluster->getSecretByName('passwords');

        $this->assertTrue($k8sSecret->isSynced());

        $k8sSecret
            ->removeData('postgres')
            ->addData('root', 'secret');

        $k8sSecret->createOrUpdate();

        $this->assertTrue($k8sSecret->isSynced());

        $this->assertEquals('v1', $k8sSecret->getApiVersion());
        $this->assertEquals('passwords', $k8sSecret->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sSecret->getLabels());
        $this->assertEquals(['root' => base64_encode('secret')], $k8sSecret->getData(false));
        $this->assertEquals(['root' => 'secret'], $k8sSecret->getData(true));
    }

    public function runDeletionTests(): void
    {
        $k8sSecret = $this->cluster->getSecretByName('passwords');

        $this->assertTrue($k8sSecret->delete());

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getSecretByName('passwords');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->secret()->watchAll(function ($type, $secret) {
            if ($secret->getName() === 'passwords') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->secret()->watchByName('passwords', fn($type, $secret): bool => $secret->getName() === 'passwords', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
