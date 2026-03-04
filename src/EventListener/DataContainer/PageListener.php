<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BackendHelperBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;


class PageListener {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var Contao\CoreBundle\Routing\ScopeMatcher
     */
    private ScopeMatcher $scopeMatcher;


    public function __construct( RequestStack $requestStack, ScopeMatcher $scopeMatcher ) {

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }


    /**
     * Add fields for backend helper
     *
     * @param Contao\DataContainer $dc
     *
     * @Callback(table="tl_page", target="config.onload")
     * @Callback(table="tl_article", target="config.onload")
     */
    public function addBackendHelperFields( $dc ) {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || !$this->scopeMatcher->isBackendRequest($request) ) {
            return;
        }

        $pm = PaletteManipulator::create()
            ->addField(['bh_info'], 'expert_legend', 'append')
        ;

        foreach( $GLOBALS['TL_DCA'][$dc->table]['palettes'] as $key => $value ) {

            if( in_array($key, ['__selector__']) ) {
                continue;
            }

            $pm->applyToPalette($key, $dc->table);
        }
    }


    /**
     * Sets the new label callback with respect to any previously added ones
     *
     * @param Contao\DataContainer $dc
     *
     * @Callback(table="tl_page", target="config.onload")
     * @Callback(table="tl_article", target="config.onload")
     */
    public function setLabelCallback( $dc ) {

        $previousCallback = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['label_callback'] ?? null;

        $callback = [$this, 'addBackendHelperInfos'];

        $GLOBALS['TL_DCA'][$dc->table]['list']['label']['label_callback'] = function () use ($callback, $previousCallback) {

            $args = \func_get_args();
            $result = null;

            if( \is_callable($previousCallback) || \is_array($previousCallback) ) {
                $result = $this->executeCallback($previousCallback, $args);
            }

            return $this->executeCallback($callback, [$args, $result]);
        };
    }


    /**
     * Sets the new label callback with respect to any previously added ones
     * for tl_article specifically. Needed since all the tl_page callbacks do not get triggered
     * if we're in tl_article
     *
     * @param Contao\DataContainer $dc
     *
     * @Callback(table="tl_article", target="config.onload")
     */
    public function setLabelCallbackForPagesInArticles( $dc ) {

        $dc->table = 'tl_page';
        $this->setLabelCallback($dc);
        $dc->table = 'tl_article';
    }


    /**
     * @param callable|array<string, string> $callback
     * @param array<mixed> $args
     *
     * @return mixed
     */
    private function executeCallback( $callback, array $args ) {

        if( \is_array($callback) ) {

            return \call_user_func_array(
                [System::importStatic($callback[0]), $callback[1]],
                $args,
            );
        }

        return $callback(...$args);
    }


    /**
     * Add backend helper information to the label
     *
     * @param array $args
     * @param string $label
     *
     * @return string
     */
    public function addBackendHelperInfos( array $args, string $label ): string {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || !$this->scopeMatcher->isBackendRequest($request) ) {
            return $label;
        }

        if( !empty($args[0]['bh_info']) ) {
            $label .= '<span class="bh_info">' . $args[0]['bh_info'] . '</span>';
        }

        return $label;
    }
}
