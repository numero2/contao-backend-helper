<?php

/**
 * Backend Helper Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BackendHelperBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\DataContainer;
use Contao\StringUtil;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;


class A11yEnforcerListener {


    private RequestStack $requestStack;
    private VirtualFilesystemInterface $filesStorage;
    private TranslatorInterface $translator;
    private bool $a11yEnabled;
    private array $a11yImages;


    public function __construct( RequestStack $requestStack, VirtualFilesystemInterface $filesStorage, TranslatorInterface $translator, bool $a11yEnabled, array $a11yImages=[] ) {

        $this->requestStack = $requestStack;
        $this->filesStorage = $filesStorage;
        $this->translator = $translator;
        $this->a11yEnabled = $a11yEnabled;
        $this->a11yImages = $a11yImages;
    }


    /**
     * Dynamically registers a11y save callbacks for all configured image fields
     * when their DCA table is loaded.
     *
     * @param string $table
     */
    #[AsHook('loadDataContainer')]
    public function registerCallbacks( string $table ): void {

        if( !$this->a11yEnabled ) {
            return;
        }

        foreach( $this->a11yImages as $image ) {

            $parts = explode('.', $image['field'], 2);

            if( count($parts) !== 2 || $parts[0] !== $table ) {
                continue;
            }

            $field = $parts[1];
            $existing = $GLOBALS['TL_DCA'][$table]['fields'][$field]['save_callback'] ?? [];

            if( !in_array([self::class, 'checkA11yRequirements'], $existing, true) ) {
                $GLOBALS['TL_DCA'][$table]['fields'][$field]['save_callback'][] = [self::class, 'checkA11yRequirements'];
            }
        }
    }


    /**
     * Validates the a11y requirements for the current field value on save.
     * Automatically detects whether the field holds a single or multiple images
     * via the DCA eval flag and applies the configured requirements accordingly.
     *
     * @param mixed $value
     * @param Contao\DataContainer $dc
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function checkA11yRequirements( $value, DataContainer $dc ) {

        if( !$this->a11yEnabled || empty($value) ) {
            return $value;
        }

        $config = $this->findImageConfig($dc->table . '.' . $dc->field);

        if( !$config || (!$config['require_alt'] && !$config['require_caption'] && !$config['require_title']) ) {
            return $value;
        }

        $request = $this->requestStack->getCurrentRequest();

        if( !$this->matchesFilter($config['filter'] ?? [], $request) ) {
            return $value;
        }

        $isMultiple = $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['multiple'] ?? false;

        if( $isMultiple ) {
            $this->validateMultipleImages($value, $config);
        } else {
            $uuidObject = Uuid::isValid($value) ? Uuid::fromString($value) : Uuid::fromBinary($value);
            $this->validateSingleImageA11y($uuidObject, $config, $dc, $request);
        }

        return $value;
    }


    /**
     * Returns the image config entry for the given field identifier (e.g. "tl_content.singleSRC"),
     * or null if no entry is configured for that field.
     *
     * @param string $field
     *
     * @return array|null
     */
    private function findImageConfig( string $field ): ?array {

        foreach( $this->a11yImages as $image ) {
            if( ($image['field'] ?? '') === $field ) {
                return $image;
            }
        }

        return null;
    }


    /**
     * Returns true if the current request URI matches at least one of the given regex patterns,
     * or if no patterns are defined.
     *
     * @param array $filter
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return bool
     */
    private function matchesFilter( array $filter, Request $request ): bool {

        if( empty($filter) ) {
            return true;
        }

        $uri = $request->getRequestUri();

        foreach( $filter as $pattern ) {
            if( preg_match('~' . $pattern . '~', $uri) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Formats an array of filenames into a comma-separated string, capped at 3 entries.
     * Any additional files are indicated as "(+N)".
     *
     * @param array $files
     *
     * @return string
     */
    private function formatFileList( array $files ): string {

        $shown = array_slice($files, 0, 3);
        $result = implode(', ', $shown);

        $remaining = count($files) - 3;

        if( $remaining > 0 ) {
            $result .= ' (+' . $remaining . ')';
        }

        return $result;
    }


    /**
     * Checks all files in a multi-image field and collects missing alt texts and captions.
     * Throws a combined exception listing up to 3 affected filenames per violation type.
     *
     * @param mixed $value
     * @param array $config
     *
     * @throws Exception
     */
    private function validateMultipleImages( $value, array $config ): void {

        $uuids = StringUtil::deserialize($value, true);

        $missingAlt = [];
        $missingCaption = [];
        $missingTitle = [];

        foreach( $uuids as $uuid ) {

            if( empty($uuid) ) {
                continue;
            }

            $item = $this->filesStorage->get(Uuid::fromBinary($uuid));

            if( !$item ) {
                continue;
            }

            $hasAlt = false;
            $hasCaption = false;
            $hasTitle = false;

            $localized = ($item->getExtraMetadata()->getLocalized()?->all()) ?? [];

            foreach( $localized as $lang => $meta ) {

                if( $meta->getAlt() !== '' ) {
                    $hasAlt = true;
                }

                if( $meta->getCaption() !== '' ) {
                    $hasCaption = true;
                }

                if( $meta->getTitle() !== '' ) {
                    $hasTitle = true;
                }
            }

            $filename = basename($item->getPath());

            if( $config['require_alt'] && !$hasAlt ) {
                $missingAlt[] = $filename;
            }

            if( $config['require_caption'] && !$hasCaption ) {
                $missingCaption[] = $filename;
            }

            if( $config['require_title'] && !$hasTitle ) {
                $missingTitle[] = $filename;
            }
        }

        $errors = [];

        if( !empty($missingAlt) ) {
            $errors[] = sprintf($this->translator->trans('ERR.backend_helper.a11y_multi_require_alt', [], 'contao_default'), $this->formatFileList($missingAlt));
        }

        if( !empty($missingCaption) ) {
            $errors[] = sprintf($this->translator->trans('ERR.backend_helper.a11y_multi_require_caption', [], 'contao_default'), $this->formatFileList($missingCaption));
        }

        if( !empty($missingTitle) ) {
            $errors[] = sprintf($this->translator->trans('ERR.backend_helper.a11y_multi_require_title', [], 'contao_default'), $this->formatFileList($missingTitle));
        }

        if( !empty($errors) ) {
            throw new Exception(implode("\n", $errors));
        }
    }


    /**
     * Validates the a11y requirements for a single image and throws an exception
     * if any required metadata (alt text, caption) is missing.
     *
     * @param Symfony\Component\Uid\Uuid $uuidObject
     * @param array $config
     * @param Contao\DataContainer $dc
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @throws Exception
     */
    private function validateSingleImageA11y( Uuid $uuidObject, array $config, DataContainer $dc, Request $request ): void {

        $item = $this->filesStorage->get($uuidObject);

        if( !$item ) {
            return;
        }

        $hasAlt = false;
        $hasCaption = false;
        $hasTitle = false;

        // check for metadata within filesystem
        $localized = ($item->getExtraMetadata()->getLocalized()?->all()) ?? [];

        foreach( $localized as $lang => $meta ) {

            if( $meta->getAlt() !== '' ) {
                $hasAlt = true;
            }

            if( $meta->getCaption() !== '' ) {
                $hasCaption = true;
            }

            if( $meta->getTitle() !== '' ) {
                $hasTitle = true;
            }
        }

        // check for metadata in current save request
        if( !$hasAlt || !$hasCaption || !$hasTitle ) {

            $activeRecord = $dc->getActiveRecord();
            $id = $activeRecord['id'];

            if( $request->get('overwriteMeta_'.$id) == '1' || $request->get('overwriteMeta') == '1' || $activeRecord['overwriteMeta'] ) {

                if( !$hasAlt ) {
                    $hasAlt = (bool) ($request->get('alt') || $request->get('alt_'.$id) || $activeRecord['alt']);
                }

                if( !$hasCaption ) {
                    $hasCaption = (bool) ($request->get('caption') || $request->get('caption_'.$id) || $activeRecord['caption']);
                }

                if( !$hasTitle ) {
                    $hasTitle = (bool) ($request->get('imageTitle') || $request->get('imageTitle_'.$id) || $activeRecord['imageTitle']);
                }
            }
        }

        $errors = [];

        if( $config['require_alt'] && !$hasAlt ) {
            $errors[] = $this->translator->trans('ERR.backend_helper.a11y_img_require_alt', [], 'contao_default');
        }

        if( $config['require_caption'] && !$hasCaption ) {
            $errors[] = $this->translator->trans('ERR.backend_helper.a11y_img_require_caption', [], 'contao_default');
        }

        if( $config['require_title'] && !$hasTitle ) {
            $errors[] = $this->translator->trans('ERR.backend_helper.a11y_img_require_title', [], 'contao_default');
        }

        if( !empty($errors) ) {
            throw new Exception(implode("\n", $errors));
        }
    }
}
