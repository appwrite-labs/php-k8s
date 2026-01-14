<?php

namespace RenokiCo\PhpK8s\Traits;

use RenokiCo\PhpK8s\Instances\Affinity;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Instances\Expression;
use RenokiCo\PhpK8s\Instances\Probe;
use RenokiCo\PhpK8s\Instances\ResourceMetric;
use RenokiCo\PhpK8s\Instances\ResourceObject;
use RenokiCo\PhpK8s\Instances\Rule;
use RenokiCo\PhpK8s\Instances\Subject;
use RenokiCo\PhpK8s\Instances\Volume;
use RenokiCo\PhpK8s\Instances\Webhook;

trait InitializesInstances
{
    /**
     * Create a new container instance.
     */
    public static function container(array $attributes = []): \RenokiCo\PhpK8s\Instances\Container
    {
        return new Container($attributes);
    }

    /**
     * Create a new probe instance.
     */
    public static function probe(array $attributes = []): \RenokiCo\PhpK8s\Instances\Probe
    {
        return new Probe($attributes);
    }

    /**
     * Create a new metric instance.
     */
    public static function metric(array $attributes = []): \RenokiCo\PhpK8s\Instances\ResourceMetric
    {
        return new ResourceMetric($attributes);
    }

    /**
     * Create a new object instance.
     */
    public static function object(array $attributes = []): \RenokiCo\PhpK8s\Instances\ResourceObject
    {
        return new ResourceObject($attributes);
    }

    /**
     * Create a new rule instance.
     */
    public static function rule(array $attributes = []): \RenokiCo\PhpK8s\Instances\Rule
    {
        return new Rule($attributes);
    }

    /**
     * Create a new subject instance.
     */
    public static function subject(array $attributes = []): \RenokiCo\PhpK8s\Instances\Subject
    {
        return new Subject($attributes);
    }

    /**
     * Create a new volume instance.
     */
    public static function volume(array $attributes = []): \RenokiCo\PhpK8s\Instances\Volume
    {
        return new Volume($attributes);
    }

    /**
     * Create a new affinity instance.
     */
    public static function affinity(array $attributes = []): \RenokiCo\PhpK8s\Instances\Affinity
    {
        return new Affinity($attributes);
    }

    /**
     * Create a new expression instance.
     */
    public static function expression(array $attributes = []): \RenokiCo\PhpK8s\Instances\Expression
    {
        return new Expression($attributes);
    }

    /**
     * Create a new webhook instance.
     */
    public static function webhook(array $attributes = []): \RenokiCo\PhpK8s\Instances\Webhook
    {
        return new Webhook($attributes);
    }
}
