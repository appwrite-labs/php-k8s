<?php

namespace RenokiCo\PhpK8s\Test;

use RenokiCo\PhpK8s\Exceptions\KubeConfigBaseEncodedDataInvalid;
use RenokiCo\PhpK8s\Exceptions\KubeConfigClusterNotFound;
use RenokiCo\PhpK8s\Exceptions\KubeConfigContextNotFound;
use RenokiCo\PhpK8s\Exceptions\KubeConfigUserNotFound;
use RenokiCo\PhpK8s\Kinds\K8sResource;
use RenokiCo\PhpK8s\KubernetesCluster;

class KubeConfigTest extends TestCase
{
    private string $tempFolder;

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFolder = __DIR__.DIRECTORY_SEPARATOR.'temp';
        KubernetesCluster::setTempFolder($this->tempFolder);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['KUBECONFIG']);
    }

    public function test_kube_config_from_yaml_file_with_base64_encoded_ssl(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube');

        [
            'verify' => $caPath,
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ] = $kubernetesCluster->getClient()->getConfig();

        $tempFilePath = $this->tempFolder.DIRECTORY_SEPARATOR.'ctx-minikube-minikube-httpsminikube8443-';

        $this->assertSame($tempFilePath.'ca-cert.pem', $caPath);
        $this->assertSame($tempFilePath.'client-cert.pem', $certPath);
        $this->assertSame($tempFilePath.'client-key.pem', $keyPath);

        $this->assertEquals("some-ca\n", file_get_contents($caPath));
        $this->assertEquals("some-cert\n", file_get_contents($certPath));
        $this->assertEquals("some-key\n", file_get_contents($keyPath));
    }

    public function test_kube_config_from_yaml_file_with_paths_to_ssl(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-2');

        [
            'verify' => $caPath,
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals('/path/to/.minikube/ca.crt', $caPath);
        $this->assertEquals('/path/to/.minikube/client.crt', $certPath);
        $this->assertEquals('/path/to/.minikube/client.key', $keyPath);
    }

    public function test_kube_config_from_yaml_file_with_skip_tols(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-skip-tls');

        [
            'verify' => $verify,
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ] = $kubernetesCluster->getClient()->getConfig();

        $this->assertFalse($verify);
        $this->assertEquals('/path/to/.minikube/client3.crt', $certPath);
        $this->assertEquals('/path/to/.minikube/client3.key', $keyPath);
    }

    public function test_cluster_can_get_correct_config_for_token_socket_connection(): void
    {
        $kubernetesCluster = KubernetesCluster::fromUrl('http://127.0.0.1:8080')->loadTokenFromFile(__DIR__.'/cluster/token.txt');

        $reflectionMethod = new \ReflectionMethod($kubernetesCluster, 'buildStreamContextOptions');

        $options = $reflectionMethod->invoke($kubernetesCluster);

        $this->assertEquals([
            'http' => [
                'header' => [
                    'Authorization: Bearer some-token',
                ],
            ],
            'ssl' => [
            ],
        ], $options);
    }

    public function test_cluster_can_get_correct_config_for_user_pass_socket_connection(): void
    {
        $kubernetesCluster = KubernetesCluster::fromUrl('http://127.0.0.1:8080')->httpAuthentication('some-user', 'some-password');

        $reflectionMethod = new \ReflectionMethod($kubernetesCluster, 'buildStreamContextOptions');

        $options = $reflectionMethod->invoke($kubernetesCluster);

        $this->assertEquals([
            'http' => [
                'header' => [
                    'Authorization: Basic c29tZS11c2VyOnNvbWUtcGFzc3dvcmQ=',
                ],
            ],
            'ssl' => [
            ],
        ], $options);
    }

    public function test_cluster_can_get_correct_config_for_ssl_socket_connection(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-2');

        $reflectionMethod = new \ReflectionMethod($kubernetesCluster, 'buildStreamContextOptions');

        $options = $reflectionMethod->invoke($kubernetesCluster);

        $this->assertEquals([
            'http' => [
                'header' => [],
            ],
            'ssl' => [
                'cafile' => '/path/to/.minikube/ca.crt',
                'local_cert' => '/path/to/.minikube/client.crt',
                'local_pk' => '/path/to/.minikube/client.key',
            ],
        ], $options);
    }

    public function test_kube_config_from_yaml_cannot_load_if_no_cluster(): void
    {
        $this->expectException(KubeConfigClusterNotFound::class);

        KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-without-cluster');
    }

    public function test_kube_config_from_yaml_cannot_load_if_no_user(): void
    {
        $this->expectException(KubeConfigUserNotFound::class);

        KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-without-user');
    }

    public function test_kube_config_from_yaml_cannot_load_if_wrong_context(): void
    {
        $this->expectException(KubeConfigContextNotFound::class);

        KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'inexistent-context');
    }

    public function test_kube_config_from_yaml_invalid_base64_ca(): void
    {
        $this->expectException(KubeConfigBaseEncodedDataInvalid::class);

        KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig.yaml', 'minikube-invalid-base64-ca');
    }

    public function test_http_authentication(): void
    {
        $kubernetesCluster = KubernetesCluster::fromUrl('http://127.0.0.1:8080')->httpAuthentication('some-user', 'some-password');

        ['auth' => $auth] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals(['some-user', 'some-password'], $auth);
    }

    public function test_bearer_token_authentication(): void
    {
        $kubernetesCluster = KubernetesCluster::fromUrl('http://127.0.0.1:8080')->loadTokenFromFile(__DIR__.'/cluster/token.txt');

        ['headers' => ['authorization' => $token]] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals('Bearer some-token', $token);
    }

    public function test_in_cluster_config(): void
    {
        $kubernetesCluster = KubernetesCluster::inClusterConfiguration();

        [
            'headers' => ['authorization' => $token],
            'verify' => $caPath,
        ] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals('Bearer some-token', $token);
        $this->assertEquals('/var/run/secrets/kubernetes.io/serviceaccount/ca.crt', $caPath);
        $this->assertEquals('some-namespace', K8sResource::$defaultNamespace);

        K8sResource::setDefaultNamespace('default');
    }

    /**
     * @dataProvider environmentVariableContextProvider
     */
    public function test_from_environment_variable(?string $context = null, string $expectedDomain = ''): void
    {
        $_SERVER['KUBECONFIG'] = __DIR__.'/cluster/kubeconfig.yaml::'.__DIR__.'/cluster/kubeconfig-2.yaml';

        $kubernetesCluster = KubernetesCluster::fromKubeConfigVariable($context);

        $this->assertSame(sprintf('https://%s:8443/?', $expectedDomain), $kubernetesCluster->getCallableUrl('/', []));
    }

    /**
     * @return \Iterator<(array<int, null> | array<int, string>)>
     */
    public function environmentVariableContextProvider(): iterable
    {
        yield [null, 'minikube'];
        yield ['minikube-2', 'minikube-2'];
        yield ['minikube-3', 'minikube-3'];
    }

    public function test_kube_config_from_array_with_base64_encoded_ssl(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigArray(yaml_parse_file(__DIR__.'/cluster/kubeconfig.yaml'), 'minikube');

        [
            'verify' => $caPath,
            'cert' => $certPath,
            'ssl_key' => $keyPath,
        ] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals("some-ca\n", file_get_contents($caPath));
        $this->assertEquals("some-cert\n", file_get_contents($certPath));
        $this->assertEquals("some-key\n", file_get_contents($keyPath));
    }

    public function test_kube_config_from_yaml_file_with_cmd_auth_as_json(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig-command.yaml', 'minikube');

        ['headers' => ['authorization' => $token]] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals('Bearer some-token', $token);
    }

    public function test_kube_config_from_yaml_file_with_cmd_auth_as_string(): void
    {
        $kubernetesCluster = KubernetesCluster::fromKubeConfigYamlFile(__DIR__.'/cluster/kubeconfig-command.yaml', 'minikube-2');

        ['headers' => ['authorization' => $token]] = $kubernetesCluster->getClient()->getConfig();

        $this->assertEquals('Bearer some-token', $token);
    }
}
