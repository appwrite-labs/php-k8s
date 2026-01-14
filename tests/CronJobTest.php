<?php

namespace RenokiCo\PhpK8s\Test;

use Carbon\Carbon;
use Cron\CronExpression;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\K8s;
use RenokiCo\PhpK8s\Kinds\K8sCronJob;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\ResourcesList;

class CronJobTest extends TestCase
{
    public function test_cronjob_build(): void
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
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setTTL(3600)
            ->setTemplate($k8sPod);

        $k8sCronJob = $this->cluster->cronjob()
            ->setName('pi')
            ->setLabels(['tier' => 'backend'])
            ->setAnnotations(['perl/annotation' => 'yes'])
            ->setJobTemplate($k8sJob)
            ->setSchedule(CronExpression::factory('* * * * *'));

        $this->assertEquals('batch/v1', $k8sCronJob->getApiVersion());
        $this->assertEquals('pi', $k8sCronJob->getName());
        $this->assertEquals(['tier' => 'backend'], $k8sCronJob->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $k8sCronJob->getAnnotations());
        $this->assertEquals('Never', $k8sPod->getRestartPolicy());

        $this->assertInstanceOf(K8sJob::class, $k8sCronJob->getJobTemplate());
        $this->assertInstanceOf(CronExpression::class, $k8sCronJob->getSchedule());
    }

    public function test_cronjob_from_yaml(): void
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

        $cronjob = $this->cluster->fromYamlFile(__DIR__.'/yaml/cronjob.yaml');

        $this->assertEquals('batch/v1', $cronjob->getApiVersion());
        $this->assertEquals('pi', $cronjob->getName());
        $this->assertEquals(['tier' => 'backend'], $cronjob->getLabels());
        $this->assertEquals(['perl/annotation' => 'yes'], $cronjob->getAnnotations());
        $this->assertEquals('Never', $k8sPod->getRestartPolicy());

        $this->assertInstanceOf(K8sJob::class, $cronjob->getJobTemplate());
        $this->assertInstanceOf(CronExpression::class, $cronjob->getSchedule());
    }

    public function test_cronjob_api_interaction(): void
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
        $busybox = K8s::container()
            ->setName('busybox-exec')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh', '-c', 'sleep 30']);

        $k8sPod = $this->cluster->pod()
            ->setName('sleep')
            ->setContainers([$busybox])
            ->restartOnFailure()
            ->neverRestart();

        $job = $this->cluster->job()
            ->setName('sleeper')
            ->setLabels(['tier' => 'useless'])
            ->setAnnotations(['perl/annotation' => 'no'])
            ->setTTL(3600)
            ->setTemplate($k8sPod);

        $cronjob = $this->cluster->cronjob()
            ->setName('periodic-sleep')
            ->setLabels(['tier' => 'useless'])
            ->setAnnotations(['perl/annotation' => 'no'])
            ->setJobTemplate($job)
            ->setSchedule(CronExpression::factory('* * * * *'));

        $this->assertFalse($cronjob->isSynced());
        $this->assertFalse($cronjob->exists());

        $cronjob = $cronjob->createOrUpdate();

        $this->assertTrue($cronjob->isSynced());
        $this->assertTrue($cronjob->exists());

        $this->assertInstanceOf(K8sCronJob::class, $cronjob);

        $this->assertEquals('batch/v1', $cronjob->getApiVersion());
        $this->assertEquals('periodic-sleep', $cronjob->getName());
        $this->assertEquals(['tier' => 'useless'], $cronjob->getLabels());
        $this->assertEquals(['perl/annotation' => 'no'], $cronjob->getAnnotations());
        $this->assertEquals('Never', $k8sPod->getRestartPolicy());

        $this->assertInstanceOf(K8sJob::class, $cronjob->getJobTemplate());
        $this->assertInstanceOf(CronExpression::class, $cronjob->getSchedule());

        $cronjob->refresh();

        $activeJobs = $cronjob->getActiveJobs();

        // This check is sensitive to ensuring the jobs take some time to complete.
        while ($cronjob->getActiveJobs()->count() === 0) {
            dump(sprintf('Waiting for the cronjob %s to have active jobs...', $cronjob->getName()));
            sleep(1);
            $cronjob->refresh();
            $activeJobs = $cronjob->getActiveJobs();
        }

        $job = $activeJobs->first();

        while (! $job->hasCompleted()) {
            dump(sprintf('Waiting for pods of %s to finish executing...', $job->getName()));
            sleep(1);
            $job->refresh();
        }

        $this->assertInstanceOf(Carbon::class, $cronjob->getLastSchedule());
        $this->assertTrue($cronjob->getLastSchedule()->gt(Carbon::now()->subSeconds(60)));
    }

    public function runGetAllTests(): void
    {
        $cronjobs = $this->cluster->getAllCronJobs();

        $this->assertInstanceOf(ResourcesList::class, $cronjobs);

        foreach ($cronjobs as $cronjob) {
            $this->assertInstanceOf(K8sCronJob::class, $cronjob);

            $this->assertNotNull($cronjob->getName());
        }
    }

    public function runGetTests(): void
    {
        $cronjob = $this->cluster->getCronJobByName('periodic-sleep');

        $this->assertInstanceOf(K8sCronJob::class, $cronjob);

        $this->assertTrue($cronjob->isSynced());

        $this->assertEquals('batch/v1', $cronjob->getApiVersion());
        $this->assertEquals('periodic-sleep', $cronjob->getName());
        $this->assertEquals(['tier' => 'useless'], $cronjob->getLabels());
        $this->assertEquals(['perl/annotation' => 'no'], $cronjob->getAnnotations());

        $this->assertInstanceOf(K8sJob::class, $cronjob->getJobTemplate());
    }

    public function runUpdateTests(): void
    {
        $cronjob = $this->cluster->getCronJobByName('periodic-sleep');

        $this->assertTrue($cronjob->isSynced());

        $cronjob->setAnnotations([]);

        $cronjob->createOrUpdate();

        $this->assertTrue($cronjob->isSynced());

        $this->assertEquals('batch/v1', $cronjob->getApiVersion());
        $this->assertEquals('periodic-sleep', $cronjob->getName());
        $this->assertEquals(['tier' => 'useless'], $cronjob->getLabels());
        $this->assertEquals([], $cronjob->getAnnotations());

        $this->assertInstanceOf(K8sJob::class, $cronjob->getJobTemplate());
    }

    public function runDeletionTests(): void
    {
        $cronjob = $this->cluster->getCronJobByName('periodic-sleep');

        $this->assertTrue($cronjob->delete());

        while ($cronjob->exists()) {
            dump(sprintf('Awaiting for cronjob %s to be deleted...', $cronjob->getName()));
            sleep(1);
        }

        $this->expectException(KubernetesAPIException::class);

        $this->cluster->getCronJobByName('periodic-sleep');
    }

    public function runWatchAllTests(): void
    {
        $watch = $this->cluster->cronjob()->watchAll(function ($type, $cronjob) {
            if ($cronjob->getName() === 'periodic-sleep') {
                return true;
            }
        }, ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }

    public function runWatchTests(): void
    {
        $watch = $this->cluster->cronjob()->watchByName('periodic-sleep', fn($type, $cronjob): bool => $cronjob->getName() === 'periodic-sleep', ['timeoutSeconds' => 10]);

        $this->assertTrue($watch);
    }
}
