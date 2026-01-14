<?php

namespace RenokiCo\PhpK8s;

use Illuminate\Support\Collection;
use RenokiCo\PhpK8s\Traits\Resource\HasAttributes;

class ResourcesList extends Collection
{
    use HasAttributes;

    public function __construct($items, $metadata)
    {
        parent::__construct($items);
        foreach ($metadata as $key => $value) {
            $this->setAttribute('metadata.' . $key, $value);
        }
    }

    public function getResourceVersion(): ?string
    {
        return $this->getAttribute('metadata.resourceVersion');
    }
}
