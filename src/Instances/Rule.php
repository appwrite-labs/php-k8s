<?php

namespace RenokiCo\PhpK8s\Instances;

class Rule extends Instance
{
    /**
     * Add a new API Group.
     *
     * @return $this
     */
    public function addApiGroup(string $apiGroup)
    {
        return $this->addToAttribute('apiGroups', $apiGroup);
    }

    /**
     * Batch-add multiple API groups.
     *
     * @param string[] $apiGroups
     * @return $this
     */
    public function addApiGroups(array $apiGroups): static
    {
        foreach ($apiGroups as $apiGroup) {
            $this->addApiGroup($apiGroup);
        }

        return $this;
    }

    /**
     * Set the API groups to core.
     *
     * @return $this
     */
    public function core(): static
    {
        return $this->addApiGroups(['']);
    }

    /**
     * Add a new resource to the list.
     *
     * @return $this
     */
    public function addResource(string $resource)
    {
        if (class_exists($resource)) {
            $resource = $resource::getPlural();
        }

        return $this->addToAttribute('resources', $resource);
    }

    /**
     * Batch-add multiple resources.
     *
     * @return $this
     */
    public function addResources(array $resources): static
    {
        foreach ($resources as $resource) {
            $this->addResource($resource);
        }

        return $this;
    }

    /**
     * Add a new resource name to the list.
     *
     * @return $this
     */
    public function addResourceName(string $name)
    {
        return $this->addToAttribute('resourceNames', $name);
    }

    /**
     * Batch-add multiple resource names.
     *
     * @param  array  $resources
     * @return $this
     */
    public function addResourceNames(array $resourceNames): static
    {
        foreach ($resourceNames as $resourceName) {
            $this->addResourceName($resourceName);
        }

        return $this;
    }

    /**
     * Add a new verb to the list.
     *
     * @return $this
     */
    public function addVerb(string $verb)
    {
        return $this->addToAttribute('verbs', $verb);
    }

    /**
     * Batch-add multiple verbs.
     *
     * @return $this
     */
    public function addVerbs(array $verbs): static
    {
        foreach ($verbs as $verb) {
            $this->addVerb($verb);
        }

        return $this;
    }
}
