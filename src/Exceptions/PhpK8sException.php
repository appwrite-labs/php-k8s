<?php

namespace RenokiCo\PhpK8s\Exceptions;

use Exception;

class PhpK8sException extends Exception
{
    /**
     * Initialize the exception.
     *
     * @param  string|null  $message
     * @param  int  $code
     */
    public function __construct($message = null, $code = 0, protected ?array $payload = null)
    {
        parent::__construct($message, $code);
    }

    /**
     * Get the payload instance.
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }
}
