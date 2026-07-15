<?php

namespace RenokiCo\PhpK8s\Test;

class DeleteOptionsTest extends TestCase
{
    public function test_propagation_policy_is_a_top_level_delete_options_field()
    {
        $job = $this->cluster->job()->setName('reap');

        $job->syncWith([
            'metadata' => [
                'name' => 'reap',
                'uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900',
                'resourceVersion' => '12345',
            ],
        ]);

        $options = $job->deleteOptions(null, 'Foreground');

        $this->assertSame('DeleteOptions', $options['kind']);
        $this->assertSame('v1', $options['apiVersion']);
        $this->assertSame('Foreground', $options['propagationPolicy']);
        $this->assertSame(['uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900'], $options['preconditions']);
        $this->assertArrayNotHasKey('propagationPolicy', $options['preconditions']);
        $this->assertArrayNotHasKey('gracePeriodSeconds', $options['preconditions']);
        $this->assertArrayNotHasKey('gracePeriodSeconds', $options);
    }

    public function test_grace_period_is_a_top_level_delete_options_field()
    {
        $job = $this->cluster->job()->setName('reap');

        $job->syncWith([
            'metadata' => [
                'name' => 'reap',
                'uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900',
            ],
        ]);

        $options = $job->deleteOptions(30, 'Background');

        $this->assertSame('Background', $options['propagationPolicy']);
        $this->assertSame(30, $options['gracePeriodSeconds']);
        $this->assertSame(['uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900'], $options['preconditions']);
    }

    public function test_preconditions_are_omitted_without_a_resource_uid()
    {
        $job = $this->cluster->job()->setName('reap');

        $options = $job->deleteOptions(null, 'Foreground');

        $this->assertSame('Foreground', $options['propagationPolicy']);
        $this->assertArrayNotHasKey('preconditions', $options);
    }

    public function test_delete_options_payload_shape_matches_the_kubernetes_api()
    {
        $job = $this->cluster->job()->setName('reap');

        $job->syncWith([
            'metadata' => [
                'name' => 'reap',
                'uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900',
            ],
            'spec' => [
                'template' => [
                    'spec' => [
                        'containers' => [],
                    ],
                ],
            ],
        ]);

        $payload = json_decode(json_encode($job->deleteOptions(null, 'Foreground')), true);

        $this->assertSame(
            [
                'apiVersion' => 'v1',
                'kind' => 'DeleteOptions',
                'propagationPolicy' => 'Foreground',
                'preconditions' => [
                    'uid' => 'aa11bb22-cc33-dd44-ee55-ff6677889900',
                ],
            ],
            $payload
        );

        $this->assertArrayNotHasKey('spec', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
    }
}
