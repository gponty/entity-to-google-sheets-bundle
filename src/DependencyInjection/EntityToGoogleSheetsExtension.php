<?php

namespace Gponty\EntityToGoogleSheetsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\AbstractExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class EntityToGoogleSheetsExtension extends AbstractExtension
{
    public function loadExtension(array $config, ContainerBuilder $container, \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $loader): void
    {
        $loader->import('../Resources/config/services.yaml');
    }
}