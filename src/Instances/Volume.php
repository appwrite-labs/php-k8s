<?php

namespace RenokiCo\PhpK8s\Instances;

use RenokiCo\PhpK8s\Kinds\K8sConfigMap;
use RenokiCo\PhpK8s\Kinds\K8sSecret;
use stdClass;

class Volume extends Instance
{
    /**
     * Create an empty directory volume.
     *
     * @return $this
     */
    public function emptyDirectory(string $name)
    {
        return $this->setAttribute('name', $name)
            ->setAttribute('emptyDir', new stdClass);
    }

    /**
     * Load a ConfigMap volume.
     *
     * @return $this
     */
    public function fromConfigMap(K8sConfigMap $k8sConfigMap)
    {
        return $this->setAttribute('name', $k8sConfigMap->getName() . '-volume')
            ->setAttribute('configMap', ['name' => $k8sConfigMap->getName()]);
    }

    /**
     * Attach a volume from a secret file.
     *
     * @return $this
     */
    public function fromSecret(K8sSecret $k8sSecret)
    {
        return $this->setAttribute('name', $k8sSecret->getName() . '-secret-volume')
            ->setAttribute('secret', ['secretName' => $k8sSecret->getName()]);
    }

    /**
     * Create a GCE Persistent Disk instance.
     *
     * @return $this
     */
    public function gcePersistentDisk(string $diskName, string $fsType = 'ext4')
    {
        return $this->setAttribute('name', $diskName . '-volume')
            ->setAttribute('gcePersistentDisk', ['pdName' => $diskName, 'fsType' => $fsType]);
    }

    /**
     * Create a AWS EBS instance.
     *
     * @return $this
     */
    public function awsEbs(string $volumeId, string $fsType = 'ext4')
    {
        return $this->setAttribute('name', $volumeId . '-volume')
            ->setAttribute('awsElasticBlockStore', ['volumeID' => $volumeId, 'fsType' => $fsType]);
    }

    /**
     * Mount the volume to a specific path.
     */
    public function mountTo(string $mountPath, ?string $subPath = null): \RenokiCo\PhpK8s\Instances\MountedVolume
    {
        return MountedVolume::from($this)->mountTo($mountPath, $subPath);
    }
}
