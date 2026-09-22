<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Patches\JsonMergePatch;
use RenokiCo\PhpK8s\Patches\JsonPatch;

class PatchPayloadTest extends TestCase
{
    public function test_json_patch_array_values_coerce_nested_empty_maps()
    {
        $payload = $this->cluster->statefulSet()->toJsonPatchPayload([
            [
                'op' => 'replace',
                'path' => '/spec/template/spec/volumes',
                'value' => [
                    ['name' => 'wal', 'emptyDir' => []],
                ],
            ],
        ]);

        $this->assertStringContainsString('"emptyDir":{}', $payload);
        $this->assertStringNotContainsString('"emptyDir":[]', $payload);
    }

    public function test_json_patch_object_values_coerce_nested_empty_maps()
    {
        $patch = (new JsonPatch)->replace('/spec/template/spec/volumes', [
            ['name' => 'wal', 'emptyDir' => []],
        ]);

        $payload = $this->cluster->statefulSet()->toJsonPatchPayload($patch);

        $this->assertStringContainsString('"emptyDir":{}', $payload);
        $this->assertStringNotContainsString('"emptyDir":[]', $payload);
    }

    public function test_json_patch_top_level_empty_list_value_stays_list()
    {
        $payload = $this->cluster->configmap()->toJsonPatchPayload([
            ['op' => 'replace', 'path' => '/metadata/finalizers', 'value' => []],
        ]);

        $this->assertStringContainsString('"value":[]', $payload);
    }

    public function test_json_patch_values_respect_exempted_list_fields()
    {
        $payload = $this->cluster->persistentVolume()->toJsonPatchPayload([
            [
                'op' => 'replace',
                'path' => '/spec',
                'value' => [
                    'accessModes' => [],
                    'mountOptions' => [],
                    'nodeAffinity' => [],
                ],
            ],
        ]);

        $this->assertStringContainsString('"accessModes":[]', $payload);
        $this->assertStringContainsString('"mountOptions":[]', $payload);
        $this->assertStringContainsString('"nodeAffinity":{}', $payload);
    }

    public function test_json_patch_nested_finalizers_stay_lists_while_maps_coerce()
    {
        $payload = $this->cluster->configmap()->toJsonPatchPayload([
            [
                'op' => 'replace',
                'path' => '/metadata',
                'value' => [
                    'finalizers' => [],
                    'labels' => [],
                ],
            ],
        ]);

        $this->assertStringContainsString('"finalizers":[]', $payload);
        $this->assertStringContainsString('"labels":{}', $payload);
    }

    public function test_json_patch_string_values_containing_empty_array_literal_are_preserved()
    {
        $script = 'foreach (glob("/x/*") ?: [] as $file) { echo $file; }';

        $payload = $this->cluster->pod()->toJsonPatchPayload([
            [
                'op' => 'replace',
                'path' => '/spec/containers/0/command',
                'value' => ['php', '-r', $script],
            ],
        ]);

        $this->assertStringNotContainsString('?: {}', $payload);

        $decoded = json_decode($payload, true);

        $this->assertSame($script, $decoded[0]['value'][2]);
    }

    public function test_json_patch_scalar_and_valueless_operations_are_untouched()
    {
        $payload = $this->cluster->deployment()->toJsonPatchPayload([
            ['op' => 'replace', 'path' => '/spec/replicas', 'value' => 3],
            ['op' => 'remove', 'path' => '/metadata/labels/deprecated'],
            ['op' => 'add', 'path' => '/metadata/labels/app', 'value' => null],
        ]);

        $this->assertSame(
            '[{"op":"replace","path":"\/spec\/replicas","value":3},'
            .'{"op":"remove","path":"\/metadata\/labels\/deprecated"},'
            .'{"op":"add","path":"\/metadata\/labels\/app","value":null}]',
            $payload
        );
    }

    public function test_empty_json_patch_encodes_as_list()
    {
        $this->assertSame('[]', $this->cluster->configmap()->toJsonPatchPayload([]));
        $this->assertSame('[]', $this->cluster->configmap()->toJsonPatchPayload(new JsonPatch));
    }

    public function test_json_merge_patch_document_coerces_nested_empty_maps()
    {
        $payload = $this->cluster->statefulSet()->toJsonMergePatchPayload([
            'spec' => [
                'template' => [
                    'spec' => [
                        'volumes' => [
                            ['name' => 'wal', 'emptyDir' => []],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('"emptyDir":{}', $payload);
        $this->assertStringNotContainsString('"emptyDir":[]', $payload);
    }

    public function test_json_merge_patch_object_document_coerces_nested_empty_maps()
    {
        $patch = new JsonMergePatch([
            'spec' => ['selector' => []],
        ]);

        $payload = $this->cluster->service()->toJsonMergePatchPayload($patch);

        $this->assertStringContainsString('"selector":{}', $payload);
    }

    public function test_json_merge_patch_clearing_finalizers_stays_list()
    {
        $payload = $this->cluster->configmap()->toJsonMergePatchPayload([
            'metadata' => ['finalizers' => []],
        ]);

        $this->assertSame('{"metadata":{"finalizers":[]}}', $payload);
    }

    public function test_json_merge_patch_clearing_conditions_stays_list()
    {
        $payload = $this->cluster->deployment()->toJsonMergePatchPayload([
            'status' => ['conditions' => []],
        ]);

        $this->assertSame('{"status":{"conditions":[]}}', $payload);
    }

    public function test_empty_json_merge_patch_encodes_as_object()
    {
        $this->assertSame('{}', $this->cluster->configmap()->toJsonMergePatchPayload([]));
        $this->assertSame('{}', $this->cluster->configmap()->toJsonMergePatchPayload(new JsonMergePatch));
    }
}
