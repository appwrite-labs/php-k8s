<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\K8s;

class JsonPayloadTest extends TestCase
{
    public function test_string_values_containing_empty_array_literal_are_not_corrupted()
    {
        $container = K8s::container()
            ->setName('backup')
            ->setImage('public.ecr.aws/docker/library/php', '8.4')
            ->setCommand(['php', '-r', 'foreach (glob("/x/*") ?: [] as $file) { echo $file; }']);

        $pod = $this->cluster->pod()
            ->setName('backup')
            ->setContainers([$container]);

        $job = $this->cluster->job()
            ->setName('backup')
            ->setTemplate($pod);

        $payload = $job->toJsonPayload();
        $decoded = json_decode($payload, true);

        $command = $decoded['spec']['template']['spec']['containers'][0]['command'][2];

        $this->assertSame('foreach (glob("/x/*") ?: [] as $file) { echo $file; }', $command);
        $this->assertStringNotContainsString('?: {}', $payload);
    }

    public function test_generic_colon_space_bracket_string_is_preserved()
    {
        $container = K8s::container()
            ->setName('echo')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh', '-c', 'echo arr: [] done']);

        $pod = $this->cluster->pod()
            ->setName('echo')
            ->setContainers([$container]);

        $decoded = json_decode($pod->toJsonPayload(), true);

        $this->assertSame('echo arr: [] done', $decoded['spec']['containers'][0]['command'][2]);
    }

    public function test_non_empty_list_arrays_stay_lists()
    {
        $container = K8s::container()
            ->setName('echo')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh', '-c', 'true']);

        $pod = $this->cluster->pod()
            ->setName('echo')
            ->setContainers([$container]);

        $decoded = json_decode($pod->toJsonPayload(), true);

        $this->assertTrue(array_is_list($decoded['spec']['containers']));
        $this->assertCount(1, $decoded['spec']['containers']);
    }

    public function test_empty_map_fields_are_coerced_to_objects()
    {
        $configMap = $this->cluster->configmap()
            ->setName('settings')
            ->setLabels([])
            ->setData([]);

        $payload = $configMap->toJsonPayload();

        $this->assertStringContainsString('"data": {}', $payload);
        $this->assertStringContainsString('"labels": {}', $payload);
    }

    public function test_exempted_list_fields_stay_empty_arrays()
    {
        $storageClass = $this->cluster->storageClass()
            ->setName('standard')
            ->setProvisioner('csi.example.com');

        $storageClass->setAttribute('mountOptions', []);
        $storageClass->setAttribute('allowedTopologies', []);
        $storageClass->setAttribute('accessModes', []);

        $payload = $storageClass->toJsonPayload();

        $this->assertStringContainsString('"mountOptions": []', $payload);
        $this->assertStringContainsString('"allowedTopologies": []', $payload);
        $this->assertStringContainsString('"accessModes": []', $payload);
    }

    public function test_nested_empty_map_deep_in_spec_is_coerced()
    {
        $container = K8s::container()
            ->setName('echo')
            ->setImage('public.ecr.aws/docker/library/busybox')
            ->setCommand(['/bin/sh', '-c', 'true']);

        $pod = $this->cluster->pod()
            ->setName('echo')
            ->setContainers([$container]);

        $pod->setAttribute('spec.nodeSelector', []);

        $payload = $pod->toJsonPayload();

        $this->assertStringContainsString('"nodeSelector": {}', $payload);
    }
}
