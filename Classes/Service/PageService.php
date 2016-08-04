<?php
namespace Mindshape\MindshapeSeo\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Daniel Dorndorf <dorndorf@mindshape.de>, mindshape GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Mindshape\MindshapeSeo\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TimeTracker\NullTimeTracker;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * @package mindshape_seo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PageService implements SingletonInterface
{
    const TREE_DEPTH_INFINITY = -1;
    const TREE_DEPTH_DEFAULT = 2;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     */
    protected $pageRepository;

    /**
     * @var int
     */
    protected static $pageTreeRoot = 0;

    /**
     * @var int
     */
    protected static $pageTreeDepth = 0;

    /**
     * @var bool
     */
    protected $hasFrontendController = true;

    /**
     * @return PageService
     * @throws \Mindshape\MindshapeSeo\Service\Exception
     */
    public function __construct()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = $objectManager->get(ConfigurationManager::class);
        $this->pageRepository = $objectManager->get(PageRepository::class);

        if ('BE' === TYPO3_MODE) {
            if (!is_object($GLOBALS['TT'])) {
                $GLOBALS['TT'] = GeneralUtility::makeInstance(NullTimeTracker::class);
                $GLOBALS['TT']->start();
            }

            try {
                $GLOBALS['TSFE'] = $objectManager->get(
                    TypoScriptFrontendController::class,
                    $GLOBALS['TYPO3_CONF_VARS'],
                    GeneralUtility::_GET('id'),
                    GeneralUtility::_GET('type')
                );

                $GLOBALS['TSFE']->connectToDB();
                $GLOBALS['TSFE']->initFEuser();
                $GLOBALS['TSFE']->determineId();
                $GLOBALS['TSFE']->initTemplate();
                $GLOBALS['TSFE']->getConfigArray();

                if (ExtensionManagementUtility::isLoaded('realurl')) {
                    $_SERVER['HTTP_HOST'] = BackendUtility::firstDomainRecord(
                        BackendUtility::BEgetRootLine(GeneralUtility::_GET('id'))
                    );
                }
            } catch (\Exception $exception) {
                $this->hasFrontendController = false;
            }
        } elseif ('FE' !== TYPO3_MODE) {
            throw new Exception('Illegal TYPO3_MODE');
        }

        $configurationManager->setContentObject(
            $objectManager->get(ContentObjectRenderer::class)
        );

        $this->uriBuilder = $objectManager->get(UriBuilder::class);
        $this->uriBuilder->injectConfigurationManager($configurationManager);
    }

    /**
     * @return bool
     */
    public function hasFrontendController()
    {
        return $this->hasFrontendController;
    }

    /**
     * Creates a link to a single page
     *
     * @param int $pageId
     * @param bool $absolute
     * @param int $sysLanguageUid
     * @return string
     */
    public function getPageLink($pageId, $absolute = false, $sysLanguageUid = 0)
    {
        return $this->uriBuilder
            ->reset()
            ->setTargetPageUid($pageId)
            ->setCreateAbsoluteUri($absolute)
            ->setArguments(
                0 < $sysLanguageUid ? array('L' => $sysLanguageUid) : array()
            )
            ->buildFrontendUri();
    }

    /**
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @return array|false
     */
    public function getPage($pageUid, $sysLanguageUid = 0)
    {
        if (0 < $sysLanguageUid) {
            $overlay = $this->pageRepository->getPageOverlay((int) $pageUid, $sysLanguageUid);

            return 0 < count($overlay) ? $overlay : false;
        } else {
            return $this->pageRepository->getPage((int) $pageUid);
        }
    }

    /**
     * @return array
     */
    public function getCurrentPage()
    {
        return $this->getPage($GLOBALS['TSFE']->id);
    }

    /**
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param string $customUrl
     * @param $useGoogleBreadcrumb
     * @return array
     */
    public function getPageMetaData($pageUid, $sysLanguageUid = 0, $customUrl = '', $useGoogleBreadcrumb = false)
    {
        $page = $this->getPage($pageUid, $sysLanguageUid);

        $pageUrl = $this->getPageLink($pageUid, true, $sysLanguageUid);
        $previewUrl = $pageUrl;

        if ('' !== $customUrl && '/' === substr($customUrl, -1, 1)) {
            $customUrl = substr($customUrl, 0, -1);
        }

        if ($useGoogleBreadcrumb) {
            $rootline = $this->getRootlineReverse($pageUid);

            $googleBreadcrumb = '' !== $customUrl ? $customUrl : GeneralUtility::getIndpEnv('HTTP_HOST');

            foreach ($rootline as $index => $parentPage) {
                $googleBreadcrumb .= $index < count($rootline) ? ' > ' : '';
                $googleBreadcrumb .= $parentPage['title'];
            }

            $previewUrl = $googleBreadcrumb;
        } elseif (!empty($customUrl)) {
            $previewUrl = $customUrl . $this->getPageLink($pageUid, false, $sysLanguageUid);
        }

        return array(
            'uid' => $page['uid'],
            'title' => empty($page['nav_title']) ? $page['title'] : $page['nav_title'],
            'disableTitleAttachment' => (bool) $page['mindshapeseo_disable_title_attachment'],
            'url' => $pageUrl,
            'previewUrl' => $previewUrl,
            'canonicalUrl' => !empty($page['mindshapeseo_canonical']) ?
                $this->getPageLink(
                    $page['mindshapeseo_canonical'],
                    true,
                    $GLOBALS['TSFE']->sys_language_uid
                ) :
                null,
            'meta' => array(
                'author' => $page['author'],
                'contact' => $page['author_email'],
                'description' => $page['description'],
                'focusKeyword' => $page['mindshapeseo_focus_keyword'],
                'robots' => array(
                    'noindex' => (bool) $page['mindshapeseo_no_index'],
                    'nofollow' => (bool) $page['mindshapeseo_no_follow'],
                    'noindexInherited' => $this->pageInheritedProperty((int) $page['uid'], 'mindshapeseo_no_index_recursive'),
                    'nofollowInherited' => $this->pageInheritedProperty((int) $page['uid'], 'mindshapeseo_no_follow_recursive'),
                ),
            ),
            'facebook' => array(
                'title' => $page['mindshapeseo_ogtitle'],
                'url' => $page['mindshapeseo_ogurl'],
                'description' => $page['mindshapeseo_ogdescription'],
            ),
        );
    }

    /**
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param string $customUrl
     * @return array
     */
    public function getSubpagesMetaData($pageUid, $sysLanguageUid = 0, $customUrl = '')
    {
        $metadata = array();

        foreach ($this->getSubPagesFromPageUid($pageUid) as $subPage) {
            if (1 !== (int) $subPage['doktype'] && 4 !== (int) $subPage['doktype']) {
                continue;
            }

            if ((int) $subPage['uid'] !== $pageUid) {
                $metadata[] = $this->getPageMetaData($subPage['uid'], $sysLanguageUid, $customUrl);
            }
        }

        return $metadata;
    }

    /**
     * @param int $pageUid
     * @return array
     */
    public function getRootline($pageUid = null)
    {
        $pages = array();

        $pageUid = null === $pageUid ? GeneralUtility::_GET('id') : $pageUid;

        foreach ($this->pageRepository->getRootLine($pageUid) as $page) {
            $pages[] = $this->getPage($page['uid']);
        }

        return $pages;
    }

    /**
     * @param int $pageUid
     * @return array
     */
    public function getRootlineReverse($pageUid = null)
    {
        $rootline = $this->getRootline($pageUid);
        array_pop($rootline);
        return array_reverse($rootline);
    }

    /**
     * @param int $pageUid
     * @return array|bool
     */
    public function getRootPage($pageUid)
    {
        $rootline = $this->pageRepository->getRootLine($pageUid);

        if (array_key_exists(1, $rootline)) {
            return $this->pageRepository->getPage($rootline[1]['uid']);
        } else {
            return false;
        }
    }

    /**
     * @param int $pageUid
     * @return array
     */
    public function getSubPageUidsFromPageUid($pageUid)
    {
        /** @var QueryGenerator $queryGenerator */
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);

        return GeneralUtility::trimExplode(
            ',',
            $queryGenerator->getTreeList($pageUid, 9999999, 0, 1)
        );
    }

    /**
     * @param int $pageUid
     * @return array
     */
    public function getSubPagesFromPageUid($pageUid)
    {
        $pages = array();

        foreach ($this->getSubPageUidsFromPageUid($pageUid) as $uid) {
            $pages[] = $this->pageRepository->getPage($uid);
        }

        return $pages;
    }

    /**
     * @param int $pageUid
     * @param int $depth
     * @param int $sysLanguageUid
     * @param string $customUrl
     * @param bool $useGoogleBreadcrumb
     * @return array
     */
    public function getPageMetadataTree($pageUid, $depth = self::TREE_DEPTH_DEFAULT, $sysLanguageUid = 0, $customUrl = '', $useGoogleBreadcrumb = false)
    {
        $page = $this->getPage($pageUid);

        /** @var PageTreeView $tree */
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init();
        $tree->table = 'pages LEFT JOIN pages_language_overlay ON pages.uid = pages_language_overlay.pid';
        $tree->clause = ' AND pages.deleted = 0 AND (pages.doktype = 1 OR pages.doktype = 4) AND ' . $GLOBALS['BE_USER']->getPagePermsClause(1);

        if (0 < $sysLanguageUid) {
            $tree->clause .= ' AND pages_language_overlay.sys_language_uid = ' . $sysLanguageUid;
        }

        $tree->parentField = 'pages.pid';
        $tree->fieldArray = array('pages.*');
        $tree->orderByFields = 'pages.sorting';

        /** @var IconFactory $iconFactory */
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $html = $iconFactory->getIconForRecord(
            'pages',
            $page,
            Icon::SIZE_SMALL
        );

        $tree->tree[] = array(
            'row' => $page,
            'HTML' => $html,
        );

        if (self::TREE_DEPTH_INFINITY === $depth) {
            $depth = 9999;
        }

        if (self::$pageTreeDepth === 0) {
            self::$pageTreeDepth = $depth;
        }

        $tree->getTree($pageUid, $depth);

        foreach ($tree->tree as $key => $treeItem) {
            if (
                $treeItem['hasSub'] &&
                self::$pageTreeDepth - $treeItem['invertedDepth'] === self::$pageTreeDepth - 1
            ) {
                $tree->tree[$key]['hasSub'] = false;
            }

            $tree->tree[$key]['metadata'] = $this->getPageMetaData(
                $treeItem['row']['uid'],
                $sysLanguageUid,
                $customUrl,
                $useGoogleBreadcrumb
            );
        }

        $tree->tree[0]['hasSub'] = 1 < count($tree->tree);

        return $tree->tree;
    }

    /**
     * Checks if a recursive field was set above the page
     * Returns the pid where property was set or false
     *
     * @param int $pageUid
     * @param string $property
     * @return int|bool
     */
    protected function pageInheritedProperty($pageUid, $property)
    {
        $inherited = false;
        $inheritedPageUid = false;

        foreach ($this->getRootlineReverse($pageUid) as $page) {
            $inherited = (bool) $page[$property] && $pageUid !== (int) $page['uid'] ? !$inherited : $inherited;
            $inheritedPageUid = $inherited ? (int) $page['uid'] : false;
        }

        return $inheritedPageUid;
    }
}
