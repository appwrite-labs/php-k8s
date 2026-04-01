<?php

namespace RenokiCo\PhpK8s\Patches;

class JsonPatch
{
    /**
     * The list of patch operations.
     */
    protected array $operations;

    /**
     * Create a new JsonPatch instance.
     */
    public function __construct(array $operations = [])
    {
        $this->operations = $operations;
    }

    /**
     * Serialize the patch to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->operations);
    }
}
