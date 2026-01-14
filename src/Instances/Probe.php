<?php

namespace RenokiCo\PhpK8s\Instances;

class Probe extends Instance
{
    /**
     * Initialize the class.
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = array_merge([
            'failureThreshold' => 1,
            'successThreshold' => 1,
        ], $attributes);
    }

    /**
     * Attach a command to the probe.
     *
     * @return $this
     */
    public function command(array $command)
    {
        return $this->setAttribute('exec.command', $command);
    }

    /**
     * Get the command for the probe.
     *
     * @return array|null
     */
    public function getCommand()
    {
        return $this->getAttribute('exec.command');
    }

    /**
     * Set the HTTP checks for given path and port.
     *
     * @return $this
     */
    public function http(string $path = '/healthz', int $port = 8080, array $headers = [], string $scheme = 'HTTP')
    {
        $probeData = [
            'path' => $path,
            'port' => $port,
            'scheme' => $scheme,
        ];

        if ($headers !== []) {
            $probeData['httpHeaders'] = collect($headers)->map(fn($value, $key): array => ['name' => $key, 'value' => $value])->values()->toArray();
        }

        return $this->setAttribute('httpGet', $probeData);
    }

    /**
     * Set the TCP checks for a given port.
     *
     * @return $this
     */
    public function tcp(int $port, ?string $host = null)
    {
        if ($host) {
            $this->setAttribute('tcpSocket.host', $host);
        }

        return $this->setAttribute('tcpSocket.port', $port);
    }
}
