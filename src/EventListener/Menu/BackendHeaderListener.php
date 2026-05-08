<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BackendHelperBundle\EventListener\Menu;

use Contao\BackendUser;
use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlAttributes;
use Knp\Menu\Util\MenuManipulator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;


#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD)]
class BackendHeaderListener {


    private readonly Security $security;
    private readonly TranslatorInterface $translator;
    private readonly bool $enabled;
    private readonly int $minLength;


    public function __construct( Security $security, TranslatorInterface $translator, bool $enabled=false, int $minLength=10 ) {

        $this->security = $security;
        $this->translator = $translator;
        $this->enabled = $enabled;
        $this->minLength = $minLength;
    }


    public function __invoke( MenuEvent $event ): void {

        if( !$this->enabled ) {
            return;
        }

        $user = $this->security->getUser();

        if( !($user instanceof BackendUser) ) {
            return;
        }

        $name = $event->getTree()->getName();

        if( $name !== 'headerMenu' ) {
            return;
        }

        $factory = $event->getFactory();
        $tree = $event->getTree();

        $title = $this->translator->trans('MSC.backend_helper.menu_longwords', [], 'contao_default');

        $itemButtonAttributes = (new HtmlAttributes())
            ->set('id', 'bhLwhToggle')
            ->set('type', 'button')
            ->set('title', $title)
            ->set('data-state', '')
            ->set('data-min-length', $this->minLength)
        ;

        $item = $factory
            ->createItem('backendHelperLongWords')
            ->setLabel(\sprintf('<button%s>%s</button>', $itemButtonAttributes, $title))
            ->setLinkAttribute('class', 'bh-lwh-toggle')
            ->setExtra('safe_label', true)
            ->setExtra('translation_domain', false)
        ;

        $tree->addChild($item);

        // reposition new entry
        $newIndex = array_search('manual', array_keys($tree->getChildren()));

        if( $newIndex === false ) {
            $newIndex = array_search('alerts', array_keys($tree->getChildren()));
        }

        (new MenuManipulator())->moveToPosition($item, $newIndex);
    }
}
