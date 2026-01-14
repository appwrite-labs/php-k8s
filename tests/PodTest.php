<?php

namespace RenokiCo\PhpK8s\Test;

use Illuminate\Support\Str;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class PodTest extends TestCase
{
    public function test_pod_build(): void
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ])
            ->addPort(3307, 'TCP', 'mysql-alt')
            ->setEnv(['MYSQL_ROOT_PASSWORD' => 'test']);

        $busybox = K8s::container()
            ->setName('busybox')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh']);

        $k8sPod = $this->cluster->pod()
            ->setName('mysql')
            ->setOrUpdateLabels(['tier' => 'test'])
            ->setOrUpdateLabels(['tier' => 'backend', 'type' => 'test'])
            ->setOrUpdateAnnotations(['mysql/annotation' => 'no'])
            ->setOrUpdateAnnotations(['mysql/annotation' => 'yes', 'mongodb/annotation' => 'no'])
            ->addPulledSecrets(['secret1', 'secret2'])
            ->setInitContainers([$busybox])
            ->setContainers([$mysql]);

        $this->assertEquals('v1', $k8sPod->getApiVersion());
        $this->assertEquals('mysql', $k8sPod->getName());
        $this->assertEquals(['tier' => 'backend', 'type' => 'test'], $k8sPod->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes', 'mongodb/annotation' => 'no'], $k8sPod->getAnnotations());
        $this->assertEquals([['name' => 'secret1'], ['name' => 'secret2']], $k8sPod->getPulledSecrets());
        $this->assertEquals([$busybox->toArray()], $k8sPod->getInitContainers(false));
        $this->assertEquals([$mysql->toArray()], $k8sPod->getContainers(false));

        $this->assertEquals('backend', $k8sPod->getLabel('tier'));
        $this->assertNull($k8sPod->getLabel('inexistentLabel'));

        $this->assertEquals('yes', $k8sPod->getAnnotation('mysql/annotation'));
        $this->assertEquals('no', $k8sPod->getAnnotation('mongodb/annotation'));
        $this->assertNull($k8sPod->getAnnotation('inexistentAnnot'));

        foreach ($k8sPod->getInitContainers() as $container) {
            $this->assertInstanceOf(Container::class, $container);
        }

        foreach ($k8sPod->getContainers() as $container) {
            $this->assertInstanceOf(Container::class, $container);
        }
    }

    public function test_pod_from_yaml(): void
    {
        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ])
            ->addPort(3307, 'TCP', 'mysql-alt')
            ->setEnv(['MYSQL_ROOT_PASSWORD' => 'test']);

        $busybox = K8s::container()
            ->setName('busybox')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh']);

        $pod = $this->cluster->fromYamlFile(__DIR__.'/yaml/pod.yaml');

        $this->assertEquals('v1', $pod->getApiVersion());
        $this->assertEquals('mysql', $pod->getName());
        $this->assertEquals(['tier' => 'backend'], $pod->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pod->getAnnotations());
        $this->assertEquals([$busybox->toArray()], $pod->getInitContainers(false));
        $this->assertEquals([$mysql->toArray()], $pod->getContainers(false));

        foreach ($pod->getInitContainers() as $initContainer) {
            $this->assertInstanceOf(Container::class, $initContainer);
        }

        foreach ($pod->getContainers() as $initContainer) {
            $this->assertInstanceOf(Container::class, $initContainer);
        }
    }

    public function test_pod_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
        $this->runUpdateTests();
        $this->runWatchAllTests();
        $this->runWatchTests();
        $this->runWatchLogsTests();
        $this->runGetLogsTests();
        $this->runDeletionTests();
    }

    public function test_pod_exec(): void
    {
        $busybox = K8s::container()
            ->setName('busybox-exec')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh', '-c', 'sleep 7200']);

        $k8sPod = $this->cluster->pod()
            ->setName('busybox-exec')
            ->setContainers([$busybox])
            ->createOrUpdate();

        while (! $k8sPod->isRunning()) {
            dump(sprintf('Waiting for pod %s to be up and running...', $k8sPod->getName()));
            sleep(1);
            $k8sPod->refresh();
        }

        $messages = $k8sPod->exec(['/bin/sh', '-c', 'echo 1 && echo 2 && echo 3'], 'busybox-exec');
        $desiredOutput = collect($messages)->where('channel', 'stdout')->reduce(fn(?string $carry, array $message) => $carry .= preg_replace('/\s+/', '', (string) $message['output']));
        $this->assertEquals('123', $desiredOutput);

        $k8sPod->delete();
    }

    public function test_pod_attach(): void
    {
        $mysql = K8s::container()
            ->setName('mysql-attach')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->setPorts([
                ['name' => 'mysql', 'protocol' => 'TCP', 'containerPort' => 3306],
            ])
            ->setEnv(['MYSQL_ROOT_PASSWORD' => 'test']);

        $k8sPod = $this->cluster->pod()
            ->setName('mysql-attach')
            ->setContainers([$mysql])
            ->createOrUpdate();

        while (! $k8sPod->isRunning()) {
            dump(sprintf('Waiting for pod %s to be up and running...', $k8sPod->getName()));
            sleep(1);
            $k8sPod->refresh();
        }

        $k8sPod->attach(function ($connection) use ($k8sPod): void {
            $connection->on('message', function ($message) use ($connection): void {
                $this->assertTrue(true);
                $connection->close();
            });

            $k8sPod->delete();
        });
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

        $busybox = K8s::container()
            ->setName('busybox')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh']);

        $pod = $this->cluster->pod()
            ->setName('mysql')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['mysql/annotation' => 'yes'])
            ->addPulledSecrets(['secret1', 'secret2'])
            ->setInitContainers([$busybox])
            ->setContainers([$mysql]);

        $this->assertFalse($pod->isSynced());
        $this->assertFalse($pod->exists());

        $pod = $pod->createOrUpdate();

        $this->assertTrue($pod->isSynced());
        $this->assertTrue($pod->exists());

        $this->assertInstanceOf(K8sPod::class, $pod);

        $this->assertEquals('v1', $pod->getApiVersion());
        $this->assertEquals('mysql', $pod->getName());
        $this->assertEquals(['tier' => 'backend'], $pod->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $pod->getAnnotations());

        while (! $pod->isRunning()) {
            dump(sprintf('Waiting for pod %s to be up and running...', $pod->getName()));
            sleep(1);
            $pod->refresh();
        }

        $pod->refresh();

        $this->assertStringEndsWith('busybox:latest', $pod->getInitContainer('busybox')->getImage());
        $this->assertStringEndsWith('mysql:5.7', $pod->getContainer('mysql')->getImage());

        $this->assertTrue($pod->containersAreReady());
        $this->assertTrue($pod->initContainersAreReady());

        $this->assertTrue(is_array($pod->getConditions()));
        $this->assertTrue(is_string($pod->getHostIp()));
        $this->assertCount(1, $pod->getPodIps());
        $this->assertEquals('BestEffort', $pod->getQos());

        $ipSlug = str_replace('.', '-', $pod->getPodIps()[0]['ip'] ?? '');
        $this->assertEquals(sprintf('%s.%s.pod.cluster.local', $ipSlug, $pod->getNamespace()), $pod->getClusterDns());
    }

    public function runGetAllTests(): void
    {
        $allPods = $this->cluster->getAllPods();

        $this->assertInstanceOf(ResourcesList::class, $allPods);

        foreach ($allPods as $allPod) {
            $this->assertInstanceOf(K8sPod::class, $allPod);

            $this->assertNotNull($allPod->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sPod = $this->cluster->getPodByName('mysql');

        $this->assertInstanceOf(K8sPod::class, $k8sPod);

        $this->assertTrue($k8sPod->isSynced());

        $this->assertEquals('v1', $k8sPod->getApiVersion());
        $this->assertEquals('mysql', $k8sPod->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sPod->getLabels());
        $this->assertEquals(['mysql/annotation' => 'yes'], $k8sPod->getAnnotations());
    }

    public function runUpdateTests(): void
    {
        $k8sPod = $this->cluster->getPodByName('mysql');

        $this->assertTrue($k8sPod->isSynced());

        $k8sPod->setLabels([])
            ->setAnnotations([]);

        $k8sPod->createOrUpdate();

        $this->assertTrue($k8sPod->isSynced());

        $this->assertEquals('v1', $k8sPod->getApiVersion());
        $this->assertEquals('mysql', $k8sPod->getName());
        $this->assertEquals([], $k8sPod->getLabels());
        $this->assertEquals([], $k8sPod->getAnnotations());
    }

    public function runDeletionTests(): void
    {
        $k8sPod = $this->cluster->getPodByName('mysql');

        $this->assertTrue($k8sPod->delete());

        while ($k8sPod->exists()) {
            dump(sprintf('Awaiting for pod %s to be deleted...', $k8sPod->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getPodByName('mysql');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->pod()->watchAll(function ($type, $pod) {
            if ($pod->getName() === 'mysql') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->pod()->watchByName('mysql', fn($type, $pod): bool => $pod->getName() === 'mysql', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchLogsTests(): void
    {
        $this->cluster->pod()->watchContainerLogsByName('mysql', 'mysql', function ($data) {
            // Debugging data to CI. :D
            dump($data);

            if (Str::contains($data, 'InnoDB')) {
                return true;
            }
        });
    }

    public function runGetLogsTests(): void
    {
        $logs = $this->cluster->pod()->containerLogsByName('mysql', 'mysql');

        // Debugging data to CI. :D
        dump($logs);

        $this->assertTrue((string) $logs !== '');
    }
}
