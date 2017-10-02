<?php

namespace Pumukit\GCImporterBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class PumukitGCImporterExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $permissions = array(array('role' => 'ROLE_ACCESS_GC_IMPORTER', 'description' => 'GC Importer'));
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);

        $container
              ->register('pumukit_gcimporter.client', "Pumukit\GCImporterBundle\Services\ClientService")
              ->addArgument($config['host'])
              ->addArgument($config['username'])
              ->addArgument($config['password']);
        $container
              ->register('pumukit_gcimporter.import', "Pumukit\GCImporterBundle\Services\ImportService")
              ->addArgument(new Reference('doctrine_mongodb.odm.document_manager'))
              ->addArgument(new Reference('pumukitschema.factory'))
              ->addArgument(new Reference('pumukitschema.track'))
              ->addArgument(new Reference('pumukitschema.tag'))
              ->addArgument(new Reference('pumukitschema.multimedia_object'))
              ->addArgument(new Reference('pumukit_gcimporter.client'))
              ->addArgument(new Reference('pumukit.inspection'))
              ->addArgument(new Parameter('pumukit2.locales'));
    }
}
