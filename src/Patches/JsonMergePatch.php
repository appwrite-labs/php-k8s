<?php

namespace RenokiCo\PhpK8s\Patches;

class JsonMergePatch
{
    /**
     * The partial document to merge.
     */
    protected array $patch;

    /**
     * Create a new JsonMergePatch instance.
     */
    public function __construct(array $patch = [])
    {
        $this->patch = $patch;
    }

    /**
     * Serialize the patch to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->patch);
    }
}
