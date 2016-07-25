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
use TYPO3\CMS\Core\SingletonInterface;

/**
 * @package mindshape_seo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class DomainService implements SingletonInterface
{
    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $databaseConnection;

    /**
     * @var \Mindshape\MindshapeSeo\Service\PageService
     * @inject
     */
    protected $pageService;

    /**
     * @var \Mindshape\MindshapeSeo\Domain\Repository\ConfigurationRepository
     * @inject
     */
    protected $configurationRepository;

    /**
     * @return DomainService
     */
    public function __construct()
    {
        $this->databaseConnection = $GLOBALS['TYPO3_DB'];
    }

    /**
     * @return array
     */
    public function getAvailableDomains()
    {
        $result = $this->databaseConnection->exec_SELECTgetRows(
            '*',
            'sys_domain',
            'TRIM(redirectTo) = ""'
        );

        $domains = array();

        if (is_array($result)) {
            foreach ($result as $domain) {
                $domains[] = $domain['domainName'];
            }
        }

        return $domains;
    }

    /**
     * @param int $pageUid
     * @return \Mindshape\MindshapeSeo\Domain\Model\Configuration|null
     */
    public function getPageDomainConfiguration($pageUid = null)
    {
        $configuration = null;

        foreach ($this->pageService->getRootline($pageUid) as $parentPage) {
            $result = $this->databaseConnection->exec_SELECTgetSingleRow(
                '*',
                'sys_domain',
                'TRIM(redirectTo) = "" AND pid = ' . $parentPage['uid']
            );

            if (is_array($result)) {
                $configuration = $this->configurationRepository->findByDomain($result['domainName']);

                if ($configuration instanceof Configuration) {
                    return $configuration;
                }
            }
        }

        return $this->configurationRepository->findByDomain(Configuration::DEFAULT_DOMAIN);
    }
}
