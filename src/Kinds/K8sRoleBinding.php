<?php

namespace RenokiCo\PhpK8s\Kinds;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Contracts\Watchable;
use RenokiCo\PhpK8s\Traits\Resource\HasSubjects;

class K8sRoleBinding extends K8sResource implements InteractsWithK8sCluster, Watchable
{
    use HasSubjects;

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'RoleBinding';

    /**
     * Wether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = 'rbac.authorization.k8s.io/v1';

    /**
     * Attach a Role/ClusterRole to the binding.
     *
     * @return $this
     */
    public function setRole(K8sRole $k8sRole, string $apiGroup = 'rbac.authorization.k8s.io')
    {
        return $this->setAttribute('roleRef', [
            'apiGroup' => $apiGroup,
            'kind' => $k8sRole::getKind(),
            'name' => $k8sRole->getName(),
        ]);
    }

    /**
     * Get the roleRef attribute.
     *
     * @return array|null
     */
    public function getRole()
    {
        return $this->getAttribute('roleRef');
    }
}
