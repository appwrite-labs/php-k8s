<?php

namespace RenokiCo\PhpK8s\Instances;

class MountedVolume extends Instance
{
    /**
     * Create a new mounted volume based on given volume.
     *
     * @param  \RenokiCo\PhpK8s\Instances\Volume  $volume
     * @return $this
     */
    public static function from(Volume $volume)
    {
        return (new self)->setName($volume->getName());
    }

    /**
     * Set the document as read only.
     *
     * @return $this
     */
    public function readOnly()
    {
        return $this->setReadOnly(true);
    }

    /**
     * Mount the volume to a specific path and subpath.
     *
     * @param  string  $mountPath
     * @param  string|null  $subPath
     * @return $this
     */
    public function mountTo(string $mountPath, string $subPath = null)
    {
        $this->setMountPath($mountPath);

        if ($subPath) {
            $this->setSubPath($subPath);
        }

        return $this;
    }
}
