<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BackendHelperBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;


class BackendHelperExtension extends Extension implements PrependExtensionInterface {


    /**
     * {@inheritdoc}
     */
    public function prepend( ContainerBuilder $container ): void {

        $config = $this->processConfiguration(new Configuration($this->getAlias()), $container->getExtensionConfig($this->getAlias()));

        $container->setParameter('numero2_backend_helper.a11y_enforcer.enabled', $config['a11y_enforcer']['enabled']);
        $container->setParameter('numero2_backend_helper.a11y_enforcer.images', $config['a11y_enforcer']['images'] ?? []);
        $container->setParameter('numero2_backend_helper.long_word_highlighter.enabled', $config['long_word_highlighter']['enabled']);
        $container->setParameter('numero2_backend_helper.long_word_highlighter.min_length', $config['long_word_highlighter']['min_length']);
    }


    /**
     * {@inheritdoc}
     */
    public function load( array $mergedConfig, ContainerBuilder $container ): void {

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('listener.yml');
        $loader->load('services.yml');
    }
}
