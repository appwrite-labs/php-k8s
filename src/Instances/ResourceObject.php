<?php

namespace RenokiCo\PhpK8s\Instances;

use RenokiCo\PhpK8s\Kinds\K8sResource;

class ResourceObject extends ResourceMetric
{
    /**
     * The resource metric type.
     *
     * @var string
     */
    protected static $type = 'Object';

    /**
     * Attach a resource to the object.
     *
     * @return $this
     */
    public function setResource(K8sResource $k8sResource)
    {
        return $this->setAttribute('object.describedObject', [
            'apiVersion' => $k8sResource->getApiVersion(),
            'kind' => $k8sResource::getKind(),
            'name' => $k8sResource->getName(),
        ]);
    }

    /**
     * Set average utilization for the metric.
     *
     * @param  int|string  $utilization
     * @return $this
     */
    #[\Override]
    public function averageUtilization($utilization = 50)
    {
        return $this->setAttribute('object.target.type', 'Utilization')
            ->setAttribute('object.target.averageUtilization', $utilization);
    }

    /**
     * Get the average utilization.
     *
     * @return string|int|float
     */
    #[\Override]
    public function getAverageUtilization()
    {
        return $this->getAttribute('object.target.averageUtilization', 0);
    }

    /**
     * Set average value for the metric.
     *
     * @param  string|int|float  $value
     * @return $this
     */
    #[\Override]
    public function averageValue($value)
    {
        return $this->setAttribute('object.target.type', 'AverageValue')
            ->setAttribute('object.target.averageValue', $value);
    }

    /**
     * Get the average value size.
     *
     * @return string|int|float
     */
    #[\Override]
    public function getAverageValue()
    {
        return $this->getAttribute('object.target.averageValue');
    }

    /**
     * Set the specific value for the metric.
     *
     * @param  string|int|float  $value
     * @return $this
     */
    #[\Override]
    public function value($value)
    {
        return $this->setAttribute('object.target.type', 'Value')
            ->setAttribute('object.target.value', $value);
    }

    /**
     * Get the value size.
     *
     * @return string|int|float
     */
    #[\Override]
    public function getValue()
    {
        return $this->getAttribute('object.target.value');
    }

    /**
     * Get the resource target type.
     */
    #[\Override]
    public function getType(): string
    {
        return $this->getAttribute('object.target.type', 'Utilization');
    }

    /**
     * Set the resource metric name.
     *
     * @return $this
     */
    #[\Override]
    public function setName(string $name)
    {
        return $this->setAttribute('object.metric.name', $name);
    }

    /**
     * Get the resource metric name.
     *
     * @param  string  $name
     * @return $this
     */
    #[\Override]
    public function getName()
    {
        return $this->getAttribute('object.metric.name');
    }
}
