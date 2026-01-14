<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\K8s;

class ResourceMetricTest extends TestCase
{
    public function test_cpu_resource_metric(): void
    {
        $resourceMetric = K8s::metric()->cpu()->averageUtilization(70);

        $this->assertEquals('Utilization', $resourceMetric->getType());
        $this->assertequals('cpu', $resourceMetric->getName());
        $this->assertEquals(70, $resourceMetric->getAverageUtilization());
    }

    public function test_memory_resource_metric(): void
    {
        $resourceMetric = K8s::metric()->memory()->averageValue('3Gi');

        $this->assertEquals('AverageValue', $resourceMetric->getType());
        $this->assertEquals('memory', $resourceMetric->getName());
        $this->assertEquals('3Gi', $resourceMetric->getAverageValue());
    }

    public function test_custom_metric(): void
    {
        $resourceMetric = K8s::metric()->setMetric('packets')->value(2048);

        $this->assertEquals('Value', $resourceMetric->getType());
        $this->assertEquals('packets', $resourceMetric->getName());
        $this->assertEquals(2048, $resourceMetric->getvalue());
    }
}
