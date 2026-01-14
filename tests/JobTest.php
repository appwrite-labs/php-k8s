<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class JobTest extends TestCase
{
    public function test_job_build(): void
    {
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('public.ecr.aws/docker/library/perl')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(200)']);

        $k8sPod = $this->cluster->pod()
            ->setName('perl')
            ->setContainers([$pi])
            ->restartOnFailure()
            ->neverRestart();

        $k8sJob = $this->cluster->job()
            ->setName('pi')
            ->setLabels(['tier' => 'compute'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setTTL(3600)
            ->setTemplate($k8sPod);

        $this->assertEquals('batch/v1', $k8sJob->getApiVersion());
        $this->assertEquals('pi', $k8sJob->getName());
        $this->assertEquals(['tier' => 'compute'], $k8sJob->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $k8sJob->getAnnotations());
        $this->assertEquals($k8sPod->getName(), $k8sJob->getTemplate()->getName());
        $this->assertEquals('Never', $k8sPod->getRestartPolicy());

        $this->assertInstanceOf(K8sPod::class, $k8sJob->getTemplate());
    }

    public function test_job_from_yaml(): void
    {
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('public.ecr.aws/docker/library/perl')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(200)']);

        $k8sPod = $this->cluster->pod()
            ->setName('perl')
            ->setContainers([$pi])
            ->restartOnFailure()
            ->neverRestart();

        $job = $this->cluster->fromYamlFile(__DIR__.'/yaml/job.yaml');

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'compute'], $job->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $job->getAnnotations());
        $this->assertEquals($k8sPod->getName(), $job->getTemplate()->getName());
        $this->assertEquals('Never', $k8sPod->getRestartPolicy());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());
    }

    public function test_job_api_interaction(): void
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
        $pi = K8s::container()
            ->setName('pi')
            ->setImage('public.ecr.aws/docker/library/perl', '5.36')
            ->setCommand(['perl',  '-Mbignum=bpi', '-wle', 'print bpi(200)']);

        $k8sPod = $this->cluster->pod()
            ->setName('perl')
            ->setLabels(['tier' => 'compute'])
            ->setContainers([$pi])
            ->neverRestart();

        $job = $this->cluster->job()
            ->setName('pi')
            ->setLabels(['tier' => 'compute'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setTTL(3600)
            ->setTemplate($k8sPod);

        $this->assertFalse($job->isSynced());
        $this->assertFalse($job->exists());

        $job = $job->createOrUpdate();

        $this->assertTrue($job->isSynced());
        $this->assertTrue($job->exists());

        $this->assertInstanceOf(K8sJob::class, $job);

        $this->assertEquals('batch/v1', $job->getApiVersion());
        $this->assertEquals('pi', $job->getName());
        $this->assertEquals(['tier' => 'compute'], $job->getLabels());

        $annotations = $job->getAnnotations();
        foreach (['perl/annotation' => 'yes'] as $key => $value) {
            $this->assertContains($key, array_keys($annotations), sprintf('Annotation %s missing', $key));
            $this->assertEquals($value, $annotations[$key]);
        }

        $this->assertEquals($k8sPod->getName(), $job->getTemplate()->getName());

        $this->assertInstanceOf(K8sPod::class, $job->getTemplate());

        $job->refresh();

        while (! $job->hasCompleted()) {
            dump(sprintf('Waiting for pods of %s to finish executing...', $job->getName()));
            sleep(1);
            $job->refresh();
        }

        K8sJob::selectPods(function ($job): array {
            $this->assertInstanceOf(K8sJob::class, $job);

            return ['tier' => 'compute'];
        });

        $pods = $job->getPods();
        $this->assertTrue($pods->count() > 0);

        K8sJob::resetPodsSelector();

        $pods = $job->getPods();
        $this->assertTrue($pods->count() > 0);

        foreach ($pods as $pod) {
            $this->assertInstanceOf(K8sPod::class, $pod);
        }

        $job->refresh();

        while (! $completionTime = $job->getCompletionTime()) {
            dump(sprintf('Waiting for the completion time report of %s...', $job->getName()));
            sleep(1);
            $job->refresh();
        }

        $this->assertTrue($job->getDurationInSeconds() > 0);
        $this->assertEquals(0, $job->getActivePodsCount());
        $this->assertEquals(0, $job->getFailedPodsCount());
        $this->assertEquals(1, $job->getSuccededPodsCount());

        $this->assertTrue(is_array($job->getConditions()));
    }

    public function runGetAllTests(): void
    {
        $allJobs = $this->cluster->getAllJobs();

        $this->assertInstanceOf(ResourcesList::class, $allJobs);

        foreach ($allJobs as $allJob) {
            $this->assertInstanceOf(K8sJob::class, $allJob);

            $this->assertNotNull($allJob->getName());
        }
    }

    public function runGetTests(): void
    {
        $k8sJob = $this->cluster->getJobByName('pi');

        $this->assertInstanceOf(K8sJob::class, $k8sJob);

        $this->assertTrue($k8sJob->isSynced());

        $this->assertEquals('batch/v1', $k8sJob->getApiVersion());
        $this->assertEquals('pi', $k8sJob->getName());
        $this->assertEquals(['tier' => 'compute'], $k8sJob->getLabels());

        $annotations = $k8sJob->getAnnotations();
        foreach (['perl/annotation' => 'yes'] as $key => $value) {
            $this->assertContains($key, array_keys($annotations), sprintf('Annotation %s missing', $key));
            $this->assertEquals($value, $annotations[$key]);
        }

        $this->assertInstanceOf(K8sPod::class, $k8sJob->getTemplate());
    }

    public function runUpdateTests(): void
    {
        $k8sJob = $this->cluster->getJobByName('pi');

        $this->assertTrue($k8sJob->isSynced());

        $k8sJob->setAnnotations([]);

        $k8sJob->createOrUpdate();

        $this->assertTrue($k8sJob->isSynced());

        $this->assertEquals('batch/v1', $k8sJob->getApiVersion());
        $this->assertEquals('pi', $k8sJob->getName());
        $this->assertEquals(['tier' => 'compute'], $k8sJob->getLabels());

        $this->assertInstanceOf(K8sPod::class, $k8sJob->getTemplate());
    }

    public function runDeletionTests(): void
    {
        $k8sJob = $this->cluster->getJobByName('pi');

        $this->assertTrue($k8sJob->delete());

        while ($k8sJob->exists()) {
            dump(sprintf('Awaiting for job %s to be deleted...', $k8sJob->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getJobByName('pi');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->job()->watchAll(function ($type, $job) {
            if ($job->getName() === 'pi') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->job()->watchByName('pi', fn($type, $job): bool => $job->getName() === 'pi', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
