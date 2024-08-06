<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
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
     */
    public function addBackendHelperFields( $dc ) {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || !$this->scopeMatcher->isBackendRequest($request) ) {
            return;
        }

        $pm = PaletteManipulator::create()
            ->addField(['bh_info'], 'expert_legend', 'append')
        ;

        foreach( $GLOBALS['TL_DCA']['tl_page']['palettes'] as $key => $value ) {

            if( in_array($key, ['__selector__', 'default']) ) {
                continue;
            }

            $pm->applyToPalette($key, 'tl_page');
        }
    }


    /**
     * Add backend helper information to the label
     *
	 * @param array $row
	 * @param string $label
	 * @param Contao\DataContainer $dc
	 * @param string $imageAttribute
	 * @param boolean $blnReturnImage
	 * @param boolean $blnProtected
	 * @param boolean $isVisibleRootTrailPage
	 *
	 * @return string
     *
     * @Callback(table="tl_page", target="list.label.label")
     */
	public function addBackendHelperInfos( $row, $label, DataContainer|null $dc=null, $imageAttribute='', $blnReturnImage=false, $blnProtected=false, $isVisibleRootTrailPage=false ) {

        $t = System::importStatic('tl_page');

        if( method_exists($t, 'addIcon') ) {
            $defaultLabel = $t->addIcon(...func_get_args());
        }

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || !$this->scopeMatcher->isBackendRequest($request) ) {
            return $defaultLabel;
        }

        if( !empty($row['bh_info']) ) {

            $info = '<span class="bh_info">' . $row['bh_info'] . '</span>';
            $defaultLabel = preg_replace("%</a>$%", $info.'</a>', $defaultLabel);
        }

        return $defaultLabel;
	}
}
