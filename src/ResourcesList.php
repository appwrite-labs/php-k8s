<?php

namespace RenokiCo\PhpK8s;

use Illuminate\Support\Collection;

class ResourcesList extends Collection
{
    protected ?string $resourceVersion = null;

    public static function fromResponse(array $items, array $metadata): static
    {
        $list = new static($items);
        $list->resourceVersion = $metadata['resourceVersion'] ?? null;

        return $list;
    }

    public function getResourceVersion(): ?string
    {
        return $this->resourceVersion;
    }
}
