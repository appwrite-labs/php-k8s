<?php

namespace RenokiCo\PhpK8s\Exceptions;

use GuzzleHttp\Exception\BadResponseException;

class KubernetesAPIException extends PhpK8sException
{
    public static function from(BadResponseException $exception): self
    {
        $response = $exception->getResponse();
        $body = json_decode((string) $response->getBody(), true);
        $payload = is_array($body) ? $body : null;
        $code = $payload['code'] ?? null;

        return new self(
            $exception->getMessage(),
            is_int($code) && $code > 0 ? $code : $response->getStatusCode(),
            $payload,
            $exception,
        );
    }
}
