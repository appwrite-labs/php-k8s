<?php

namespace RenokiCo\PhpK8s\Traits\Cluster;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\ResourcesList;

trait MakesHttpCalls
{
    /**
     * Get the callable URL for a specific path.
     */
    public function getCallableUrl(string $path, array $query = ['pretty' => 1]): string
    {
        /**
         * Replace any name[<number>]=value occurences with name=value
         * to support argv input.
         */
        $query = urldecode((string) preg_replace('/%5B(?:\d|[1-9]\d+)%5D=/', '=', http_build_query($query)));

        return sprintf('%s%s?%s', $this->url, $path, $query);
    }

    /**
     * Get the Guzzle Client to perform requests on.
     */
    public function getClient(): \GuzzleHttp\Client
    {
        $options = [
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
            ],
            RequestOptions::VERIFY => true,
        ];

        if (is_bool($this->verify) || is_string($this->verify)) {
            $options[RequestOptions::VERIFY] = $this->verify;
        }

        if ($this->token) {
            $options[RequestOptions::HEADERS]['authorization'] = 'Bearer ' . $this->token;
        }

        if ($this->auth) {
            $options[RequestOptions::AUTH] = $this->auth;
        }

        if ($this->cert) {
            $options[RequestOptions::CERT] = $this->cert;
        }

        if ($this->sslKey) {
            $options[RequestOptions::SSL_KEY] = $this->sslKey;
        }

        return new Client($options);
    }

    /**
     * Make a HTTP call to a given path with a method and payload.
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     */
    public function call(string $method, string $path, string $payload = '', array $query = ['pretty' => 1])
    {
        try {
            $response = $this->getClient()->request($method, $this->getCallableUrl($path, $query), [
                RequestOptions::BODY => $payload,
            ]);
        } catch (ClientException $clientException) {
            $errorPayload = json_decode((string) $clientException->getResponse()->getBody(), true);

            throw new KubernetesAPIException(
                $clientException->getMessage(),
                $errorPayload['code'] ?? 0,
                $errorPayload
            );
        }

        return $response;
    }

    /**
     * Call the API with the specified method and path.
     *
     * @return mixed
     *
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     */
    protected function makeRequest(string $operation, string $path, string $payload = '', array $query = ['pretty' => 1])
    {
        $resourceClass = $this->resourceClass;

        $method = static::$operations[$operation] ?? static::$operations[static::GET_OP];
        $response = $this->call($method, $path, $payload, $query);

        if ($operation === static::LOG_OP) {
            return (string) $response->getBody();
        }

        $json = @json_decode($response->getBody(), true);

        // If the output is not JSONable, return the response itself.
        // This can be encountered in case of a pod log request, for example,
        // where the data returned are just console logs.

        if (!$json) {
            return (string) $response->getBody();
        }

        // If the kind is a list, transform into a ResourcesList
        // collection of instances for the same class.

        if (isset($json['items'])) {
            $results = [];

            foreach ($json['items'] as $item) {
                $results[] = new $resourceClass($this, $item)->synced();
            }

            return new ResourcesList($results, $json['metadata']);
        }

        // If the items does not exist, it means the Kind
        // is the same as the current class, so pass it
        // for the payload.

        return new $resourceClass($this, $json)->synced();
    }
}
