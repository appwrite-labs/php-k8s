<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\K8s;
use stdClass;

class VolumeTest extends TestCase
{
    public function test_volume_empty_directory(): void
    {
        $volume = K8s::volume()->emptyDirectory('some-volume');

        $mountedVolume = $volume->mountTo('/some-path');

        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->addMountedVolumes([$mountedVolume])
            ->setMountedVolumes([$mountedVolume]);

        $k8sPod = K8s::pod()
            ->setName('mysql')
            ->setContainers([$mysql])
            ->addVolumes([$volume])
            ->setVolumes([$volume]);

        $this->assertEquals([
            'name' => 'some-volume',
            'emptyDir' => new stdClass,
        ], $volume->toArray());

        $this->assertEquals([
            'name' => 'some-volume',
            'mountPath' => '/some-path',
        ], $mountedVolume->toArray());

        $this->assertEquals($k8sPod->getVolumes()[0]->toArray(), $volume->toArray());
        $this->assertEquals($mysql->getMountedVolumes()[0]->toArray(), $mountedVolume->toArray());
    }

    public function test_volume_config_map(): void
    {
        $k8sConfigMap = K8s::configMap()
            ->setName('some-config-map')
            ->setData([
                'some-key' => 'some-content',
                'some-key2' => 'some-content-again',
            ]);

        $volume = K8s::volume()->fromConfigMap($k8sConfigMap);

        $mountedVolume = $volume->mountTo('/some-path', 'some-key');

        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->addMountedVolumes([$mountedVolume]);

        $k8sPod = K8s::pod()
            ->setName('mysql')
            ->setContainers([$mysql])
            ->addVolumes([$volume]);

        $this->assertEquals([
            'name' => 'some-config-map-volume',
            'configMap' => ['name' => $k8sConfigMap->getName()],
        ], $volume->toArray());

        $this->assertEquals([
            'name' => 'some-config-map-volume',
            'mountPath' => '/some-path',
            'subPath' => 'some-key',
        ], $mountedVolume->toArray());

        $this->assertEquals($k8sPod->getVolumes()[0]->toArray(), $volume->toArray());
        $this->assertEquals($mysql->getMountedVolumes()[0]->toArray(), $mountedVolume->toArray());
    }

    public function test_volume_secret(): void
    {
        $k8sSecret = K8s::secret()
            ->setName('some-secret')
            ->setData([
                'some-key' => 'some-content',
                'some-key2' => 'some-content-again',
            ]);

        $volume = K8s::volume()->fromSecret($k8sSecret);

        $mountedVolume = $volume->mountTo('/some-path', 'some-key');

        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->addMountedVolumes([$mountedVolume]);

        $k8sPod = K8s::pod()
            ->setName('mysql')
            ->setContainers([$mysql])
            ->addVolumes([$volume]);

        $this->assertEquals([
            'name' => 'some-secret-secret-volume',
            'secret' => ['secretName' => $k8sSecret->getName()],
        ], $volume->toArray());

        $this->assertEquals([
            'name' => 'some-secret-secret-volume',
            'mountPath' => '/some-path',
            'subPath' => 'some-key',
        ], $mountedVolume->toArray());

        $this->assertEquals($k8sPod->getVolumes()[0]->toArray(), $volume->toArray());
        $this->assertEquals($mysql->getMountedVolumes()[0]->toArray(), $mountedVolume->toArray());
    }

    public function test_volume_gce_pd(): void
    {
        $volume = K8s::volume()->gcePersistentDisk('some-disk', 'ext3');

        $mountedVolume = $volume->mountTo('/some-path');

        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->addMountedVolumes([$mountedVolume]);

        $k8sPod = K8s::pod()
            ->setName('mysql')
            ->setContainers([$mysql])
            ->addVolumes([$volume]);

        $this->assertEquals([
            'name' => 'some-disk-volume',
            'gcePersistentDisk' => [
                'pdName' => 'some-disk',
                'fsType' => 'ext3',
            ],
        ], $volume->toArray());

        $this->assertEquals([
            'name' => 'some-disk-volume',
            'mountPath' => '/some-path',
        ], $mountedVolume->toArray());

        $this->assertEquals($k8sPod->getVolumes()[0]->toArray(), $volume->toArray());
        $this->assertEquals($mysql->getMountedVolumes()[0]->toArray(), $mountedVolume->toArray());
    }

    public function test_volume_aws_ebs(): void
    {
        $volume = K8s::volume()->awsEbs('vol-1234', 'ext3');

        $mountedVolume = $volume->mountTo('/some-path');

        $mysql = K8s::container()
            ->setName('mysql')
            ->setImage('public.ecr.aws/docker/library/mysql', '5.7')
            ->addMountedVolumes([$mountedVolume]);

        $k8sPod = K8s::pod()
            ->setName('mysql')
            ->setContainers([$mysql])
            ->addVolumes([$volume]);

        $this->assertEquals([
            'name' => 'vol-1234-volume',
            'awsElasticBlockStore' => [
                'volumeID' => 'vol-1234',
                'fsType' => 'ext3',
            ],
        ], $volume->toArray());

        $this->assertEquals([
            'name' => 'vol-1234-volume',
            'mountPath' => '/some-path',
        ], $mountedVolume->toArray());

        $this->assertEquals($k8sPod->getVolumes()[0]->toArray(), $volume->toArray());
        $this->assertEquals($mysql->getMountedVolumes()[0]->toArray(), $mountedVolume->toArray());
    }
}
