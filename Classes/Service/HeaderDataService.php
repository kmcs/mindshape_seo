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

use Mindshape\MindshapeSeo\Domain\Model\Configuration;
use Mindshape\MindshapeSeo\Domain\Repository\ConfigurationRepository;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * @package mindshape_seo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class HeaderDataService
{
    /**
     * @var \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var \Mindshape\MindshapeSeo\Service\PageService
     */
    protected $pageService;

    /**
     * @var \Mindshape\MindshapeSeo\Service\StandaloneTemplateRendererService
     */
    protected $standaloneTemplateRendererService;

    /**
     * @var \Mindshape\MindshapeSeo\Domain\Repository\ConfigurationRepository
     */
    protected $configurationRepository;

    /**
     * @var \Mindshape\MindshapeSeo\Domain\Model\Configuration
     */
    protected $domainConfiguration;

    /**
     * @var array
     */
    protected $currentPage;

    /**
     * @var string
     */
    protected $currentDomainUrl;

    /**
     * @var string
     */
    protected $currentSitename;

    /**
     * @param PageRenderer $pageRenderer
     * @return HeaderDataService
     */
    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;

        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->pageService = $objectManager->get(PageService::class);
        $this->standaloneTemplateRendererService = $objectManager->get(StandaloneTemplateRendererService::class);
        $this->configurationRepository = $objectManager->get(ConfigurationRepository::class);

        $page = $this->pageService->getCurrentPage();

        $this->currentPage = array(
            'uid' => $page['uid'],
            'title' => $page['title'],
            'canonicalPageUid' => (int) $page['mindshapeseo_canonical'],
            'meta' => array(
                'author' => $page['author'],
                'contact' => $page['author_email'],
                'description' => $page['description'],
                'robots' => array(
                    'noindex' => (bool) $page['mindshapeseo_no_index'],
                    'nofollow' => (bool) $page['mindshapeseo_no_follow'],
                ),
            ),
            'facebook' => array(
                'title' => $page['mindshapeseo_ogtitle'],
                'url' => $page['mindshapeseo_ogurl'],
                'description' => $page['mindshapeseo_ogdescription'],
            ),
            'seo' => array(
                'noIndex' => (bool) $page['mindshapeseo_no_index'],
                'noFollow' => (bool) $page['mindshapeseo_no_follow'],
                'disableTitleAttachment' => (bool) $page['mindshapeseo_disable_title_attachment'],
            ),
        );

        $this->currentSitename = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];

        $currentDomain = GeneralUtility::getIndpEnv('HTTP_HOST');

        $this->domainConfiguration = $this->configurationRepository->findByDomain($currentDomain, true);

        $this->currentDomainUrl = $this->pageService->getPageLink(
            $GLOBALS['TSFE']->rootLine[0]['uid']
        );

        if (0 < (int) $page['mindshapeseo_ogimage']) {
            /** @var FileRepository $fileRepository */
            $fileRepository = $objectManager->get(FileRepository::class);
            /** @var ImageService $imageService */
            $imageService = $objectManager->get(ImageService::class);
            $files = $fileRepository->findByRelation('pages', 'ogimage', $page['uid']);

            if (0 < count($files)) {
                /** @var FileReference $file */
                $file = $files[0];
                /** @var ProcessedFile $processedFile */
                $processedFile = $imageService->applyProcessingInstructions(
                    $file,
                    array(
                        'crop' => $file->getReferenceProperties()['crop'],
                    )
                );

                $this->currentPage['facebook']['image'] = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/' . $processedFile->getPublicUrl();
            }
        } elseif (null !== $this->domainConfiguration->getFacebookDefaultImage()) {
            $this->currentPage['facebook']['image'] = $this->domainConfiguration->getFacebookDefaultImage()->getOriginalResource()->getPublicUrl();
        }
    }

    /**
     * @return void
     */
    public function manipulateHeaderData()
    {
        $this->attachTitleAttachment();
        $this->addMetaData();
        $this->addFacebookData();

        if (0 < $this->currentPage['canonicalPageUid']) {
            $this->addCanonicalUrl();
        }

        if ($this->domainConfiguration instanceof Configuration) {
            if ($this->domainConfiguration->getAddHreflang()) {
                $this->addHreflang();
            }

            if ($this->domainConfiguration->getAddJsonld()) {
                $this->addJsonLd();
            }

            if ('' !== $this->domainConfiguration->getGoogleAnalytics()) {
                $this->addGoogleAnalytics();
            }

            if (
                '' === $this->domainConfiguration->getGoogleAnalytics() &&
                '' !== $this->domainConfiguration->getPiwikUrl() &&
                '' !== $this->domainConfiguration->getPiwikIdsite()
            ) {
                $this->addPiwik();
            }
        }
    }

    /**
     * @return void
     */
    protected function addCanonicalUrl()
    {
        $this->pageRenderer->addHeaderData(
            '<link rel="canonical" href="' .
            $this->pageService->getPageLink(
                $this->currentPage['canonicalPageUid'],
                $GLOBALS['TSFE']->sys_language_uid
            ) .
            '"/>'
        );
    }

    /**
     * @return void
     */
    protected function attachTitleAttachment()
    {
        if (
            !$this->currentPage['seo']['disableTitleAttachment'] &&
            '' !== $this->domainConfiguration->getTitleAttachment()
        ) {
            $this->pageRenderer->setTitle(
                $this->currentPage['title'] . ' | ' . $this->domainConfiguration->getTitleAttachment()
            );
        }
    }

    /**
     * @return void
     */
    protected function addHreflang()
    {
        /** @var DatabaseConnection $databaseConnection */
        $databaseConnection = $GLOBALS['TYPO3_DB'];

        $result = $databaseConnection->exec_SELECTgetRows(
            '*',
            'sys_language l INNER JOIN pages_language_overlay o ON l.uid = o.sys_language_uid',
            'o.pid = ' . $this->currentPage['uid']
        );

        foreach ($result as $language) {
            $this->pageRenderer->addHeaderData(
                $this->renderHreflang(
                    $this->pageService->getPageLink($this->currentPage['uid'], $language['uid']),
                    $language['language_isocode']
                )
            );
        }
    }

    /**
     * @param string $url
     * @param string $languageKey
     * @return string
     */
    protected function renderHreflang($url, $languageKey)
    {
        return '<link rel="alternate" href="' . $url . '" hreflang="' . $languageKey . '"/>';
    }

    /**
     * @return void
     */
    protected function addFacebookData()
    {
        $metaData = array(
            'og:site_name' => $this->currentSitename,
            'og:url' => $this->currentPage['facebook']['url'],
            'og:title' => $this->currentPage['facebook']['title'],
            'og:description' => $this->currentPage['facebook']['description'],
        );

        if (array_key_exists('image', $this->currentPage['facebook'])) {
            $metaData['og:image'] = $this->currentPage['facebook']['image'];
        }

        $this->addMetaDataArray($metaData);
    }

    protected function addMetaData()
    {
        $robots = array();

        if (
            !$this->currentPage['meta']['robots']['noindex'] ||
            !$this->currentPage['meta']['robots']['nofollow']
        ) {
            $robots = $this->getParentRobotsMetaData();
        }

        if (
            $this->currentPage['meta']['robots']['noindex'] &&
            !in_array('noindex', $robots, true)
        ) {
            $robots[] = 'noindex';
        }

        if (
            $this->currentPage['meta']['robots']['nofollow'] &&
            !in_array('nofollow', $robots, true)
        ) {
            $robots[] = 'nofollow';
        }

        $metaData = array(
            'author' => $this->currentPage['meta']['author'],
            'contact' => $this->currentPage['meta']['contact'],
            'description' => $this->currentPage['meta']['description'],
            'robots' => implode(',', $robots),
        );

        $this->addMetaDataArray($metaData);
    }

    /**
     * @return array
     */
    protected function getParentRobotsMetaData()
    {
        $robots = array();

        $noindex = false;
        $nofollow = false;

        foreach ($this->pageService->getRootline() as $page) {
            if (!$noindex && $page['mindshapeseo_no_index_recursive']) {
                $noindex = true;

                if ($page['mindshapeseo_no_index']) {
                    $robots[] = 'noindex';
                }
            }

            if (!$nofollow && $page['mindshapeseo_no_follow_recursive']) {
                $nofollow = true;

                if ($page['mindshapeseo_no_follow']) {
                    $robots[] = 'nofollow';
                }
            }
        }

        return $robots;
    }

    /**
     * @param array $metaData
     * @return void
     */
    protected function addMetaDataArray(array $metaData)
    {
        foreach ($metaData as $property => $content) {
            if (!empty($content)) {
                $this->pageRenderer->addHeaderData(
                    $this->renderMetaTag($property, $content)
                );
            }
        }
    }

    /**
     * @param string $property
     * @param string $content
     * @return string
     */
    protected function renderMetaTag($property, $content)
    {
        return '<meta property="' . $property . '" content="' . $content . '"/>';
    }

    /**
     * @return void
     */
    protected function addGoogleAnalytics()
    {
        $view = $this->standaloneTemplateRendererService->getView('Analytics', 'Google');
        $view->assign('analyticsId', $this->domainConfiguration->getGoogleAnalytics());

        $this->pageRenderer->addHeaderData(
            $view->render()
        );
    }

    /**
     * @return void
     */
    protected function addPiwik()
    {
        $view = $this->standaloneTemplateRendererService->getView('Analytics', 'Piwik');
        $view->assignMultiple(array(
            'piwikUrl' => $this->domainConfiguration->getPiwikUrl(),
            'piwikIdSite' => $this->domainConfiguration->getPiwikIdsite(),
        ));

        $this->pageRenderer->addHeaderData(
            $view->render()
        );
    }

    /**
     * @return void
     */
    protected function addJsonLd()
    {
        $jsonLdArray = array();

        $jsonLdArray[] = $this->renderJsonWebsiteName();
        $jsonLdArray[] = $this->renderJsonLdInformation();
        $jsonLdbreadcrumb = $this->renderJsonLdBreadcrum();

        if (0 < count($jsonLdbreadcrumb['itemListElement'])) {
            $jsonLdArray[] = $jsonLdbreadcrumb;
        }

        if (0 < count($jsonLdArray)) {
            $this->pageRenderer->addHeaderData(
                '<script type="application/ld+json" data-ignore="1">' . json_encode($jsonLdArray) . '</script>'
            );
        }
    }

    /**
     * @return array
     */
    protected function renderJsonWebsiteName()
    {
        return array(
            '@context' => 'http://schema.org',
            '@type' => 'WebSite',
            'url' => '' !== $this->domainConfiguration->getJsonldCustomUrl() ?
                $this->domainConfiguration->getJsonldCustomUrl() :
                GeneralUtility::getIndpEnv('HTTP_HOST'),
        );
    }

    /**
     * @return array
     */
    protected function renderJsonLdInformation()
    {
        $jsonld = array(
            '@context' => 'http://schema.org',
            '@type' => $this->domainConfiguration->getJsonldType(),
            'url' => $this->currentDomainUrl,
            'telephone' => $this->domainConfiguration->getJsonldTelephone(),
            'faxNumber' => $this->domainConfiguration->getJsonldFax(),
            'email' => $this->domainConfiguration->getJsonldEmail(),
            'address' => array(
                '@type' => 'PostalAddress',
                'addressLocality' => $this->domainConfiguration->getJsonldAddressLocality(),
                'postalcode' => $this->domainConfiguration->getJsonldAddressPostalcode(),
                'streetAddress' => $this->domainConfiguration->getJsonldAddressStreet(),
            ),
        );

        if (null !== $this->domainConfiguration->getJsonldLogo()) {
            $jsonld['logo'] = $this->domainConfiguration
                ->getJsonldLogo()
                ->getOriginalResource()
                ->getPublicUrl();
        }

        return $jsonld;
    }

    /**
     * @return array
     */
    protected function renderJsonLdBreadcrum()
    {
        $breadcrumb = array(
            '@context' => 'http://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(),
        );

        $rootLineUids = $GLOBALS['TSFE']->rootLine;
        array_pop($rootLineUids);
        $rootLineUids = array_reverse($rootLineUids);

        foreach ($rootLineUids as $index => $page) {
            if (
                1 !== (int) $page['doktype'] &&
                4 !== (int) $page['doktype']
            ) {
                continue;
            }

            $breadcrumb['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => array(
                    '@id' => $this->pageService->getPageLink($page['uid']),
                    'name' => $page['title'],
                ),
            );
        }

        return $breadcrumb;
    }
}
