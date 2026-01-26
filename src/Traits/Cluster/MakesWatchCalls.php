<?php

namespace RenokiCo\PhpK8s\Traits\Cluster;

use Closure;
use Exception;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector as ReactSocketConnector;

/**
 * Provides watch operations using ReactPHP for non-blocking I/O.
 *
 * These methods return a ReactPHP event loop and promise, making them
 * compatible with coroutine environments like Swoole.
 */
trait MakesWatchCalls
{
    /**
     * Watch for the current resource or resource list.
     *
     * Returns the event loop and promise for async operation.
     * Call $loop->run() to execute, or integrate with your own event loop.
     *
     * @return array{0: LoopInterface, 1: PromiseInterface}
     */
    protected function watchPath(string $path, Closure $callback, array $query = ['pretty' => 1]): array
    {
        /** @var class-string<\RenokiCo\PhpK8s\Kinds\K8sResource> $resourceClass */
        $resourceClass = $this->resourceClass;
        $cluster = $this;

        return $this->createAsyncStreamConnection(
            $this->getCallableUrl($path, $query),
            function (string $line) use ($callback, $resourceClass, $cluster) {
                $data = @json_decode($line, true);

                if (! $data || ! isset($data['type'], $data['object'])) {
                    return null;
                }

                ['type' => $type, 'object' => $attributes] = $data;

                return call_user_func(
                    $callback,
                    $type,
                    new $resourceClass($cluster, $attributes)
                );
            }
        );
    }

    /**
     * Watch for the logs for the resource.
     *
     * Returns the event loop and promise for async operation.
     * Call $loop->run() to execute, or integrate with your own event loop.
     *
     * @return array{0: LoopInterface, 1: PromiseInterface}
     */
    protected function watchLogsPath(string $path, Closure $callback, array $query = ['pretty' => 1]): array
    {
        return $this->createAsyncStreamConnection(
            $this->getCallableUrl($path, $query),
            function (string $line) use ($callback) {
                return call_user_func($callback, $line."\n");
            }
        );
    }

    /**
     * Create an async streaming HTTP connection using ReactPHP.
     *
     * @param  Closure  $lineCallback  Called for each line received, return non-null to stop.
     * @return array{0: LoopInterface, 1: PromiseInterface}
     */
    protected function createAsyncStreamConnection(string $url, Closure $lineCallback): array
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        $requestPath = ($parsed['path'] ?? '/').
            (isset($parsed['query']) ? '?'.$parsed['query'] : '');

        $loop = Loop::get();
        $deferred = new Deferred;

        $connectorOptions = [
            'timeout' => $this->timeout ?? 30.0,
            'tls' => $this->buildReactTlsOptions(),
        ];

        $connector = new ReactSocketConnector($connectorOptions, $loop);

        $uri = ($scheme === 'https' ? 'tls' : 'tcp')."://{$host}:{$port}";

        $connector->connect($uri)->then(
            function (ConnectionInterface $connection) use ($requestPath, $host, $port, $lineCallback, $deferred) {
                // Build and send HTTP request
                $request = $this->buildHttpRequest($requestPath, $host, $port);
                $connection->write($request);

                $buffer = '';
                $headersParsed = false;
                $isChunked = false;
                $chunkBuffer = '';
                $expectedChunkSize = null;

                $connection->on('data', function ($chunk) use (
                    &$buffer,
                    &$headersParsed,
                    &$isChunked,
                    &$chunkBuffer,
                    &$expectedChunkSize,
                    $lineCallback,
                    $connection,
                    $deferred
                ) {
                    $buffer .= $chunk;

                    // Parse HTTP headers first
                    if (! $headersParsed) {
                        $headerEnd = strpos($buffer, "\r\n\r\n");
                        if ($headerEnd === false) {
                            return; // Wait for complete headers
                        }

                        $headers = substr($buffer, 0, $headerEnd);
                        $buffer = substr($buffer, $headerEnd + 4);
                        $headersParsed = true;

                        // Check for chunked transfer encoding
                        if (stripos($headers, 'transfer-encoding: chunked') !== false) {
                            $isChunked = true;
                        }
                    }

                    // Process body content
                    if ($isChunked) {
                        $this->processChunkedData(
                            $buffer,
                            $chunkBuffer,
                            $expectedChunkSize,
                            $lineCallback,
                            $connection,
                            $deferred
                        );
                    } else {
                        $this->processStreamData(
                            $buffer,
                            $lineCallback,
                            $connection,
                            $deferred
                        );
                    }
                });

                $connection->on('close', function () use ($deferred) {
                    $deferred->resolve(null);
                });

                $connection->on('error', function (Exception $e) use ($deferred) {
                    $deferred->reject($e);
                });
            },
            function (Exception $e) use ($deferred) {
                $deferred->reject($e);
            }
        );

        return [$loop, $deferred->promise()];
    }

    /**
     * Build HTTP request string with authentication headers.
     */
    protected function buildHttpRequest(string $path, string $host, int $port): string
    {
        $hostHeader = $port === 80 || $port === 443 ? $host : "{$host}:{$port}";

        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$hostHeader}",
            'Accept: application/json',
            'Connection: keep-alive',
        ];

        // Add authentication
        $authToken = $this->getAuthToken();
        if ($authToken) {
            $headers[] = "Authorization: Bearer {$authToken}";
        } elseif ($this->auth) {
            $headers[] = 'Authorization: Basic '.base64_encode(implode(':', $this->auth));
        }

        return implode("\r\n", $headers)."\r\n\r\n";
    }

    /**
     * Build TLS options for React socket connector.
     */
    protected function buildReactTlsOptions(): array
    {
        $tlsOptions = [];

        if (is_bool($this->verify)) {
            $tlsOptions['verify_peer'] = $this->verify;
            $tlsOptions['verify_peer_name'] = $this->verify;
        } elseif (is_string($this->verify)) {
            $tlsOptions['cafile'] = $this->verify;
        }

        if ($this->cert) {
            $tlsOptions['local_cert'] = $this->cert;
        }

        if ($this->sslKey) {
            $tlsOptions['local_pk'] = $this->sslKey;
        }

        return $tlsOptions;
    }

    /**
     * Process non-chunked streaming data.
     */
    protected function processStreamData(
        string &$buffer,
        Closure $lineCallback,
        ConnectionInterface $connection,
        Deferred $deferred
    ): void {
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $result = $lineCallback($line);
            if ($result !== null) {
                $deferred->resolve($result);
                $connection->close();

                return;
            }
        }
    }

    /**
     * Process chunked transfer encoding data.
     */
    protected function processChunkedData(
        string &$buffer,
        string &$chunkBuffer,
        ?int &$expectedChunkSize,
        Closure $lineCallback,
        ConnectionInterface $connection,
        Deferred $deferred
    ): void {
        while (true) {
            // If we don't have a chunk size, try to read one
            if ($expectedChunkSize === null) {
                $sizeEnd = strpos($buffer, "\r\n");
                if ($sizeEnd === false) {
                    return; // Wait for chunk size line
                }

                $sizeHex = substr($buffer, 0, $sizeEnd);
                $buffer = substr($buffer, $sizeEnd + 2);
                $expectedChunkSize = hexdec($sizeHex);

                // Zero size means end of stream
                if ($expectedChunkSize === 0) {
                    $connection->close();
                    $deferred->resolve(null);

                    return;
                }
            }

            // Read chunk data
            if (strlen($buffer) < $expectedChunkSize + 2) {
                return; // Wait for complete chunk + CRLF
            }

            $chunkData = substr($buffer, 0, $expectedChunkSize);
            $buffer = substr($buffer, $expectedChunkSize + 2); // Skip CRLF after chunk
            $expectedChunkSize = null;

            // Add to line buffer and process complete lines
            $chunkBuffer .= $chunkData;

            while (($pos = strpos($chunkBuffer, "\n")) !== false) {
                $line = substr($chunkBuffer, 0, $pos);
                $chunkBuffer = substr($chunkBuffer, $pos + 1);

                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $result = $lineCallback($line);
                if ($result !== null) {
                    $deferred->resolve($result);
                    $connection->close();

                    return;
                }
            }
        }
    }
}
