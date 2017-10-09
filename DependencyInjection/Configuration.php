<?php

namespace Pumukit\GCImporterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pumukit_gc_importer');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        $rootNode
          ->children()
            ->scalarNode('host')
              ->isRequired()
              ->validate()
              ->always(function($v) {
                    if (strpos($v,'http://') === 0 || strpos($v,'https://') === 0) {
                      return $v;
                    }
                    throw new InvalidTypeException('Host URL must start with http:// or https://');
                  })
              ->end()
              ->info('Galicaster Web Panel URL.')
            ->end()
            ->scalarNode('username')
              ->isRequired()
              ->info('Galicaster Web Panel Username.')
            ->end()
            ->scalarNode('password')
              ->isRequired()
              ->info('Galicaster Web Panel Password.')
            ->end()
            ->booleanNode('legacy')
              ->defaultFalse()
              ->info('If true, MMObjects are shown without pagination.')
            ->end()
          ->end()
        ;

        return $treeBuilder;
    }
}
