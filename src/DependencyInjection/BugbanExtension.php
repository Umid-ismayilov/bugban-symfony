<?php

namespace Bugban\Symfony\DependencyInjection;

use Bugban\Sdk\Bugban;
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

        // Eagerly initialize the SDK at container-build/boot time.
        Bugban::init(array(
            'api_key' => isset($config['api_key']) ? $config['api_key'] : '',
            'host' => isset($config['host']) ? $config['host'] : 'https://bugban.online',
            'environment' => isset($config['environment']) ? $config['environment'] : null,
            'release' => isset($config['release']) ? $config['release'] : null,
            'enabled' => isset($config['enabled']) ? $config['enabled'] : true,
            'capture_requests' => isset($config['capture_requests']) ? $config['capture_requests'] : false,
            'sample_rate' => isset($config['sample_rate']) ? $config['sample_rate'] : 1.0,
        ));

        // Register the exception listener as a service (EventSubscriberInterface).
        $definition = new Definition(ExceptionListener::class);
        $definition->setPublic(false);
        $definition->addTag('kernel.event_subscriber');
        $container->setDefinition('bugban.exception_listener', $definition);
    }

    public function getAlias()
    {
        return 'bugban';
    }
}
