<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BackendHelperBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use numero2\BackendHelperBundle\BackendHelperBundle;


class Plugin implements BundlePluginInterface {


    /**
     * {@inheritdoc}
     */
    public function getBundles( ParserInterface $parser ): array {

        return [
            BundleConfig::create(BackendHelperBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class
                ])
        ];
    }
}
