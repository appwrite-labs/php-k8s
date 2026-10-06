<?php

namespace RenokiCo\PhpK8s\Enums;

/**
 * Dependent deletion policies accepted by the Kubernetes DeleteOptions API.
 */
enum PropagationPolicy: string
{
    case ORPHAN = 'Orphan';
    case BACKGROUND = 'Background';
    case FOREGROUND = 'Foreground';
}
