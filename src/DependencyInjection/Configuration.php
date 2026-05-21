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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface {


    public function __construct( private readonly string $alias ) {
    }


    public function getConfigTreeBuilder(): TreeBuilder {

        $treeBuilder = new TreeBuilder($this->alias);

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('a11y_enforcer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('images')
                            ->arrayPrototype()
                                ->beforeNormalization()
                                    ->always(function( $v ) {
                                        $fieldDefaults = [
                                            'tl_content.singleSRC' => ['require_alt' => true,  'require_caption' => false, 'require_title' => false],
                                            'tl_content.multiSRC'  => ['require_alt' => false, 'require_caption' => false, 'require_title' => false],
                                        ];
                                        $field = $v['field'] ?? '';
                                        if( isset($fieldDefaults[$field]) ) {
                                            foreach( $fieldDefaults[$field] as $key => $default ) {
                                                if( !array_key_exists($key, $v) ) {
                                                    $v[$key] = $default;
                                                }
                                            }
                                        }
                                        return $v;
                                    })
                                ->end()
                                ->children()
                                    ->scalarNode('field')->isRequired()->cannotBeEmpty()->end()
                                    ->booleanNode('require_alt')->defaultTrue()->end()
                                    ->booleanNode('require_caption')->defaultFalse()->end()
                                    ->booleanNode('require_title')->defaultFalse()->end()
                                    ->arrayNode('filter')
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('long_word_highlighter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('min_length')->defaultValue(15)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
