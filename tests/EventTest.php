<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\ResourcesList;

class EventTest extends TestCase
{
    public function test_event_api_interaction(): void
    {
        $this->runCreationTests();
        $this->runGetAllTests();
        $this->runGetTests();
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

        $dep = $dep->createOrUpdate();

        $event = $dep->newEvent()
            ->setMessage('This is a test message for events.')
            ->setReason('SomeReason')
            ->setType('Normal')
            ->setName('mysql-test');

        $this->assertFalse($event->isSynced());
        $this->assertFalse($event->exists());

        $event = $event->emitOrUpdate();

        $this->assertTrue($event->isSynced());
        $this->assertTrue($event->exists());

        $this->assertInstanceOf(K8sEvent::class, $event);

        $matchedEvent = $dep->getEvents()->first(fn($ev): bool => $ev->getName() === $event->getName());

        $this->assertInstanceOf(K8sEvent::class, $matchedEvent);
        $this->assertTrue($matchedEvent->is($event));
    }

    public function runGetAllTests(): void
    {
        $allEvents = $this->cluster->getAllEvents();

        $this->assertInstanceOf(ResourcesList::class, $allEvents);

        foreach ($allEvents as $allEvent) {
            $this->assertInstanceOf(K8sEvent::class, $allEvent);

            $this->assertNotNull($allEvent->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sEvent = $this->cluster->getEventByName('mysql-test');

        $this->assertInstanceOf(K8sEvent::class, $k8sEvent);

        $this->assertTrue($k8sEvent->isSynced());
    }

    public function runDeletionTests(): void
    {
        $k8sEvent = $this->cluster->getEventByName('mysql-test');

        $this->assertTrue($k8sEvent->delete());

        while ($k8sEvent->exists()) {
            dump(sprintf('Awaiting for horizontal pod autoscaler %s to be deleted...', $k8sEvent->getName()));
            sleep(1);
        }

        while ($k8sEvent->exists()) {
            dump(sprintf('Awaiting for event %s to be deleted...', $k8sEvent->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getEventByName('mysql-test');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->event()->watchAll(function ($type, $event) {
            if ($event->getName() === 'mysql-test') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->event()->watchByName('mysql-test', fn($type, $event): bool => $event->getName() === 'mysql-test', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
