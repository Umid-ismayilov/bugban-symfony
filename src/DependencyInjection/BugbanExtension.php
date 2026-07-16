<?php

namespace Bugban\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Bugban\Symfony\EventListener\ExceptionListener;

class BugbanExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // NOTE: load() only runs when the container is (re)compiled, not on every
        // request. So we must NOT call Bugban::init() here — the compiled container
        // is cached and the static SDK client would be lost at runtime. Instead we
        // store the resolved config as a container parameter and let
        // BugbanBundle::boot() initialize the SDK on every kernel boot / request.
        $container->setParameter('bugban._config', array(
            'api_key' => isset($config['api_key']) ? $config['api_key'] : '',
            'host' => isset($config['host']) ? $config['host'] : 'https://bugban.online',
            'environment' => isset($config['environment']) ? $config['environment'] : null,
            'release' => isset($config['release']) ? $config['release'] : null,
            'enabled' => isset($config['enabled']) ? $config['enabled'] : true,
            'capture_requests' => isset($config['capture_requests']) ? $config['capture_requests'] : false,
            'capture_queries' => isset($config['capture_queries']) ? $config['capture_queries'] : true,
            'slow_query_ms' => isset($config['slow_query_ms']) ? $config['slow_query_ms'] : 1000,
            'sample_rate' => isset($config['sample_rate']) ? $config['sample_rate'] : 1.0,
            // Metadata for the one-time install ping (SDK handshake).
            'app_name' => isset($config['app_name']) ? $config['app_name'] : null,
            'framework' => 'symfony',
            'framework_version' => defined('Symfony\Component\HttpKernel\Kernel::VERSION')
                ? \Symfony\Component\HttpKernel\Kernel::VERSION
                : null,
            'sdk' => 'bugban/symfony',
        ));

        // Register the exception listener as a service (EventSubscriberInterface).
        $definition = new Definition(ExceptionListener::class);
        $definition->setPublic(false);
        $definition->addTag('kernel.event_subscriber');
        $container->setDefinition('bugban.exception_listener', $definition);
    }

    public function getAlias(): string
    {
        return 'bugban';
    }
}
