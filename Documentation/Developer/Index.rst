﻿.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _developer-manual:

Developer Manual
================

Target group: **Developers**

.. _developer-hooks:

Available Hooks
---------------

Sitemap Hooks
^^^^^^^^^^^^^

You'll find a example for a sitemap hook here: `"Classes/Hook/NewsSitemapHook.php" <https://github.com/mindshape-GmbH/mindshape_seo/blob/master/Classes/Hook/NewsSitemapHook.php>`_

**Pre-rendering of the Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemap_preRendering']

**Post-rendering of the Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemap_postRendering']

**Pre-rendering of the Index Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemapIndex_preRendering']

**Post-rendering of the Index Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemapIndex_postRendering']

**Pre-rendering of the Image Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemapImage_preRendering']

**Post-rendering of the Image Sitemap**
::
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mindshape_seo']['sitemapImage_postRendering']
