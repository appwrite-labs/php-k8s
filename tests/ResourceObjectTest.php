<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\K8s;

class ResourceObjectTest extends TestCase
{
    public function test_average_utilization_object(): void
    {
        $k8sService = $this->cluster->service()->setName('nginx');

        $resourceObject = K8s::object()
            ->setResource($k8sService)
            ->setMetric('packets-per-second')
            ->averageUtilization('1k');

        $this->assertEquals('Utilization', $resourceObject->getType());
        $this->assertEquals('packets-per-second', $resourceObject->getName());
        $this->assertEquals('1k', $resourceObject->getAverageUtilization());
    }

    public function test_averge_value_object(): void
    {
        $k8sService = $this->cluster->service()->setName('nginx');

        $resourceObject = K8s::object()
            ->setResource($k8sService)
            ->setMetric('packets-per-second')
            ->averageValue('1k');

        $this->assertEquals('AverageValue', $resourceObject->getType());
        $this->assertEquals('packets-per-second', $resourceObject->getName());
        $this->assertEquals('1k', $resourceObject->getAverageValue());
    }

    public function test_value_object(): void
    {
        $k8sService = $this->cluster->service()->setName('nginx');

        $resourceObject = K8s::object()
            ->setResource($k8sService)
            ->setMetric('packets-per-second')
            ->value('1k');

        $this->assertEquals('Value', $resourceObject->getType());
        $this->assertEquals('packets-per-second', $resourceObject->getName());
        $this->assertEquals('1k', $resourceObject->getValue());
    }
}
