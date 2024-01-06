<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\Meilisearch\Tests\Integration\IndexQueue\FrontendHelper;

use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 * (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
 */
class PageIndexerTest extends IntegrationTest
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/meilisearch',
        '../vendor/wapplersystems/meilisearch/Tests/Integration/Fixtures/Extensions/fake_extension3',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
    }

    /**
     * Executed after each test. Emptys meilisearch and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canIndexPageIntoMeilisearch(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_meilisearch.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.pages.fields {
              sortSubTitle_stringS = subtitle
              custom_stringS = TEXT
              custom_stringS.value = my text
            }
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"hello meilisearch"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $meilisearchContent, 'Document does not contain subtitle');
        self::assertStringContainsString('"custom_stringS":"my text"', $meilisearchContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageWithCustomPageTypeIntoMeilisearch(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_pagetype_into_meilisearch.csv');

        // @TODO: Check page type in fixture, currently not set to 130
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.mytype < plugin.tx_meilisearch.index.queue.pages
            plugin.tx_meilisearch.index.queue.mytype {
              allowedPageTypes = 130
              additionalWhereClause = doktype = 130
              fields.custom_stringS = TEXT
              fields.custom_stringS.value = my text from custom page type
            }
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"hello meilisearch"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"custom_stringS":"my text from custom page type"', $meilisearchContent, 'Document does not contains value build with typoscript');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexTranslatedPageToPageRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_en');
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_page.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.pages.fields.relatedPageTitles_stringM = MEILISEARCH_RELATION
            plugin.tx_meilisearch.index.queue.pages.fields.relatedPageTitles_stringM {
              localField = page_relations
              enableRecursiveValueResolution = 0
              multiValue = 1
            }
            '
        );

        $this->indexQueuedPage(2, '/en/');
        $this->indexQueuedPage(2, '/de/');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch('core_en');
        $this->waitToBeVisibleInMeilisearch('core_de');

        $meilisearchContentEn = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"Page"', $meilisearchContentEn, 'Meilisearch did not contain the english page');
        self::assertStringNotContainsString('relatedPageTitles_stringM', $meilisearchContentEn, 'There is no relation for the original, so there should not be a related field');

        $meilisearchContentDe = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_de/select?q=*:*');
        self::assertStringContainsString('"title":"Seite"', $meilisearchContentDe, 'Meilisearch did not contain the translated page');
        self::assertStringContainsString('"relatedPageTitles_stringM":["Verwandte Seite"]', $meilisearchContentDe, 'Did not get content of related field');

        $this->cleanUpMeilisearchServerAndAssertEmpty('core_en');
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexPageToCategoryRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_en');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_category.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.pages.fields.categories_stringM = MEILISEARCH_RELATION
            plugin.tx_meilisearch.index.queue.pages.fields.categories_stringM {
              localField = categories
              foreignLabelField = title
              multiValue = 1
            }
            '
        );

        $this->indexQueuedPage(10);

        $this->waitToBeVisibleInMeilisearch('core_en');

        $meilisearchContentEn = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"Sub page"', $meilisearchContentEn, 'Meilisearch did not contain the english page');
        self::assertStringContainsString('"categories_stringM":["Test"]', $meilisearchContentEn, 'There is no relation for the original, so ther should not be a related field');

        $this->cleanUpMeilisearchServerAndAssertEmpty('core_en');
    }

    /**
     * @test
     */
    public function canIndexPageIntoMeilisearchWithAdditionalFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_additional_fields_into_meilisearch.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.additionalFields {
              additional_sortSubTitle_stringS = subtitle
              additional_custom_stringS = TEXT
              additional_custom_stringS.value = my text
            }
            '
        );
        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"hello meilisearch"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $meilisearchContent, 'Document does not contain subtitle');

        // field values from index.additionalFields
        self::assertStringContainsString('"additional_sortSubTitle_stringS":"subtitle"', $meilisearchContent, 'Document does not contains value from index.additionFields');
        self::assertStringContainsString('"additional_custom_stringS":"my text"', $meilisearchContent, 'Document does not contains value from index.additionFields');
    }

    /**
     * @test
     */
    public function canIndexPageIntoMeilisearchWithAdditionalFieldsFromRootLine(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_overwrite_configuration_in_rootline.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.pages.fields.additional_stringS = TEXT
            plugin.tx_meilisearch.index.queue.pages.fields.additional_stringS.value = from rootline
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"hello subpage"', $meilisearchContent, 'Could not index subpage with custom field configuration into meilisearch');
        self::assertStringContainsString('"additional_stringS":"from rootline"', $meilisearchContent, 'Document does not contain custom field from rootline');
    }

    /**
     * @test
     */
    public function canExecutePostProcessor(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_meilisearch.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"postProcessorField_stringS":"postprocessed"', $meilisearchContent, 'Field from post processor was not added');
    }

    /**
     * @test
     */
    public function canExecuteAdditionalPageIndexer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_meilisearch.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_meilisearch.index.queue.pages.fields {
              custom_stringS = TEXT
              custom_stringS.value = my text
            }
            '
        );
        $this->indexQueuedPage(2, '/en/', ['additionalTestPageIndexer' => true]);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"custom_stringS":"my text"', $meilisearchContent, 'Field from post processor was not added');
        self::assertStringContainsString('"custom_stringS":"additional text"', $meilisearchContent, 'Field from post processor was not added');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * There is following scenario:
     *
     *  [0]
     *  |
     *  ——[20] Shared-Pages (Not root)
     *  |   |
     *  |   ——[24] FirstShared (Not root)
     *  |
     *  ——[ 1] Page (Root)
     *  |
     *  ——[14] Mount Point (to [24] to show contents from)
     *
     * @test
     */
    public function canIndexMountedPage(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage(24, '/en/', ['MP' => '24-14']);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"FirstShared (Not root)"', $meilisearchContent, 'Could not find content from mounted page in meilisearch');
    }

    /**
     * There is following scenario:
     *
     *  [0]
     *  |
     *  ——[20] Shared-Pages (Not root)
     *  |   |
     *  |   ——[44] FirstShared (Not root)
     *  |
     *  ——[ 1] Page (Root)
     *  |
     *  ——[14] Mount Point (to [24] to show contents from)
     *
     *  |
     *  ——[ 2] Page (Root)
     *  |
     *  ——[24] Mount Point (to [24] to show contents from)
     * @test
     */
    public function canIndexMultipleMountedPage(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_multiple_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage(44, '/en/', ['MP' => '44-14']);
        $this->indexQueuedPage(44, '/en/', ['MP' => '44-24']);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch();

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"numFound":2', $meilisearchContent, 'Unexpected amount of documents in the core');

        self::assertStringContainsString('/pages/44/44-14/', $meilisearchContent, 'Could not find document of first mounted page');
        self::assertStringContainsString('/pages/44/44-24/', $meilisearchContent, 'Could not find document of second mounted page');
    }

    /**
     * This Test should test, that TYPO3 CMS on FE does not die if page is not available.
     * If something goes wrong the exception must be thrown instead of dying, to make marking the items as failed possible.
     *
     * @test
     */
    public function phpProcessDoesNotDieIfPageIsNotAvailable(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/does_not_die_if_page_not_available.csv');
        $response = $this->indexQueuedPage(1636120156);

        $decodedResponse = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($decodedResponse, 'Response couldn\'t be decoded');

        $actionResults = unserialize($decodedResponse['actionResults']['indexPage']);
        self::assertFalse($actionResults['pageIndexed'] ?? null, 'Index page result not set to false as expected!');
    }

    /**
     * Executes a Frontend request within the same PHP process (possible since TYPO3 v11).
     */
    protected function indexQueuedPage(int $pageId = 1, string $siteLanguageBase = '/en/', $additionalQueryParams = [], string $domain = 'http://testone.site'): ResponseInterface
    {
        $additionalQueryParams['id'] = $pageId;
        $additionalQueryParams = array_filter($additionalQueryParams);
        $queryString = http_build_query($additionalQueryParams, '', '&');
        $cacheHash = GeneralUtility::makeInstance(CacheHashCalculator::class)->generateForParameters($queryString);
        if ($cacheHash) {
            $queryString .= '&cHash=' . $cacheHash;
        }
        $url = rtrim($domain, '/') . '/' . ltrim($siteLanguageBase, '/') . '?' . $queryString;

        // Now add the headers for item 4711 to the request
        $item = $this->getIndexQueueItem(4711);
        return $this->executePageIndexer($url, $item);
    }
}
