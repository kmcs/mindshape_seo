<?php
namespace Mindshape\MindshapeSeo\Handler;

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

use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Http\AjaxRequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * @package mindshape_seo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class AjaxHandler implements SingletonInterface
{
    /**
     * @param array $params
     * @param AjaxRequestHandler $ajaxRequestHandler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function savePage(array $params = array(), AjaxRequestHandler $ajaxRequestHandler = null)
    {
        /** @var ServerRequest $request */
        $request = $params['request'];

        if ($request instanceof ServerRequest) {
            $data = $request->getParsedBody();

            if (is_array($data)) {
                if (
                    0 < $data['pageUid'] &&
                    !empty($data['title'])
                ) {
                    $this->savePageData(
                        (int) $data['pageUid'],
                        (int) $data['sysLanguageUid'],
                        array(
                            'title' => $data['title'],
                            'description' => $data['description'],
                            'mindshapeseo_focus_keyword' => $data['focusKeyword'],
                            'mindshapeseo_no_index' => (bool) $data['noindex'] ? 1 : 0,
                            'mindshapeseo_no_follow' => (bool) $data['nofollow'] ? 1 : 0,
                        )
                    );
                } else {
                    $ajaxRequestHandler->setError('Invalid Data');
                }
            }
        }

        return $ajaxRequestHandler->render();
    }

    /**
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param array $data
     */
    protected function savePageData($pageUid, $sysLanguageUid = 0, array $data)
    {
        /** @var DatabaseConnection $databaseConnection */
        $databaseConnection = $GLOBALS['TYPO3_DB'];

        if (0 < $sysLanguageUid) {
            $pageOverlay = $databaseConnection->exec_SELECTgetSingleRow(
                'p.*',
                'pages_language_overlay p',
                'pid = ' . $pageUid . ' AND sys_language_uid = ' . $sysLanguageUid
            );

            $pageUid = (int) $pageOverlay['uid'];
        }

        $databaseConnection->exec_UPDATEquery(
            0 < $sysLanguageUid
                ? 'pages_language_overlay'
                : 'pages',
            'uid = ' . $pageUid,
            $data
        );
    }
}
