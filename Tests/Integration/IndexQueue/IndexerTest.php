<?php

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

namespace WapplerSystems\Meilisearch\Tests\Integration\IndexQueue;

use WapplerSystems\Meilisearch\IndexQueue\Indexer;
use WapplerSystems\Meilisearch\IndexQueue\Item;
use WapplerSystems\Meilisearch\IndexQueue\Queue;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Middleware\NormalizedParamsAttribute;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
 */
class IndexerTest extends IntegrationTest
{
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/meilisearch',
        '../vendor/wapplersystems/meilisearch/Tests/Integration/Fixtures/Extensions/fake_extension2',
    ];

    /**
     * @var Queue|null
     */
    protected ?Queue $indexQueue = null;

    /**
     * @var Indexer|null
     */
    protected ?Indexer $indexer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->indexer = GeneralUtility::makeInstance(Indexer::class);

        /** @var BackendUserAuthentication $beUser */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');

        $_SERVER['HTTP_HOST'] = 'test.local.typo3.org';
        $request = ServerRequestFactory::fromGlobals();
        $handlerMock = $this->createMock(RequestHandlerInterface::class);
        $normalizer = new NormalizedParamsAttribute();
        $normalizer->process($request, $handlerMock);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        unset(
            $this->indexQueue,
            $this->indexer,
        );
    }

    /**
     * This testcase should check if we can queue a custom record with MM relations.
     *
     * @test
     */
    public function canIndexItemWithMMRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_mm_relation.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);

        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"category_stringM":["the tag"]', $meilisearchContent, 'Did not find MM related tag');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    public function getTranslatedRecordDataProvider(): array
    {
        return [
            'with_l_parameter' => ['can_index_custom_translated_record_with_l_param.csv'],
            'without_l_parameter' => ['can_index_custom_translated_record_without_l_param.csv'],
            'without_l_parameter_and_content_fallback' => ['can_index_custom_translated_record_without_l_param_and_content_fallback.csv'],
        ];
    }

    /**
     * @dataProvider getTranslatedRecordDataProvider
     * @test
     */
    public function testCanIndexTranslatedCustomRecord(string $fixture): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 777);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"original"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"original2"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"url":"http://testone.site/en/?tx_foo%5Buid%5D=88', $meilisearchContent, 'Can not build typolink as expected');
        self::assertStringContainsString('"url":"http://testone.site/en/?tx_foo%5Buid%5D=777', $meilisearchContent, 'Can not build typolink as expected');

        $this->waitToBeVisibleInMeilisearch('core_de');
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_de/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $meilisearchContent, 'Could not find translated record in meilisearch document into meilisearch');
        if ($fixture === 'can_index_custom_translated_record_without_l_param_and_content_fallback.csv') {
            self::assertStringContainsString('"title":"original"', $meilisearchContent, 'Could not index  translated document into meilisearch');
            self::assertStringContainsString('"title":"original2"', $meilisearchContent, 'Could not index  translated document into meilisearch');
        } else {
            self::assertStringContainsString('"title":"translation"', $meilisearchContent, 'Could not index  translated document into meilisearch');
            self::assertStringContainsString('"title":"translation2"', $meilisearchContent, 'Could not index  translated document into meilisearch');
        }
        self::assertStringContainsString('"url":"http://testone.site/de/?tx_foo%5Buid%5D=88', $meilisearchContent, 'Can not build typolink as expected');
        self::assertStringContainsString('"url":"http://testone.site/de/?tx_foo%5Buid%5D=777', $meilisearchContent, 'Can not build typolink as expected');

        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase should check if we can queue a custom record with ordered MM relations.
     *
     * @test
     */
    public function canIndexItemWithMMRelationsInTheExpectedOrder(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_multiple_mm_relations.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);

        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the values from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContentJson = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        $meilisearchContent = json_decode($meilisearchContentJson, true);
        $meilisearchContentResponse = $meilisearchContent['response'];

        self::assertArrayHasKey('docs', $meilisearchContentResponse, 'Did not find docs in meilisearch response');

        $meilisearchDocs = $meilisearchContentResponse['docs'];

        self::assertCount(1, $meilisearchDocs, 'Could not found index document into meilisearch');
        self::assertIsArray($meilisearchDocs[0]);
        self::assertEquals('testnews', (string)$meilisearchDocs[0]['title'], 'Title of Meilisearch document is not as expected.');
        self::assertArrayHasKey('category_stringM', $meilisearchDocs[0], 'Did not find MM related tags.');
        self::assertCount(2, $meilisearchDocs[0]['category_stringM'], 'Did not find all MM related tags.');
        self::assertSame(['the tag', 'another tag'], $meilisearchDocs[0]['category_stringM']);

        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue a custom record with MM relations.
     *
     * @test
     * @todo: this test might not be working as it does not check for L parameters. Should be revised
     */
    public function canIndexTranslatedItemWithMMRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_translated_record_with_mm_relation.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch('core_de');
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_de/select?q=*:*');

        self::assertStringContainsString('"category_stringM":["translated tag"]', $meilisearchContent, 'Did not find MM related tag');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"translation"', $meilisearchContent, 'Could not index document into meilisearch');

        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase should check if we can queue a custom record with multiple MM relations.
     *
     * @test
     */
    public function canIndexMultipleMMRelatedItems(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_multiple_mm_relations.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        $decodedMeilisearchContent = json_decode($meilisearchContent);
        $tags = $decodedMeilisearchContent->response->docs[0]->tags_stringM;

        self::assertSame(['the tag', 'another tag'], $tags, 'Did not find MM related tags');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');

        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue a custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexItemWithMMRelationAndAdditionalWhere(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_mm_relationAndAdditionalWhere.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"category_stringM":["another tag"]', $meilisearchContent, 'Did not find MM related tag');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue a custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexItemWithMMRelationToATranslatedPage(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_translated_record_with_mm_relation_to_a_page.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $this->waitToBeVisibleInMeilisearch('core_de');

        $meilisearchContentEn = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        $meilisearchContentDe = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_de/select?q=*:*');

        self::assertStringContainsString('"relatedPageTitles_stringM":["Related page"]', $meilisearchContentEn, 'Can not find related page title');
        self::assertStringContainsString('"relatedPageTitles_stringM":["Translated related page"]', $meilisearchContentDe, 'Can not find translated related page title');

        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->cleanUpMeilisearchServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     *
     * @test
     */
    public function canIndexItemWithDirectRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_direct_relation.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"category_stringM":["the category"]', $meilisearchContent, 'Did not find direct related category');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"sysCategoryId_stringM":["1"]', $meilisearchContent, 'Uid of related sys_category couldn\'t be resolved by using "foreignLabelField"');
        self::assertStringContainsString('"sysCategory_stringM":["sys_category"]', $meilisearchContent, 'Label of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        self::assertStringContainsString('"sysCategoryDescription_stringM":["sys_category description"]', $meilisearchContent, 'Description of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if multiple direct relations can be resolved with the RELATION configuration
     *
     * @test
     */
    public function canIndexItemWithMultipleDirectRelation(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_multiple_direct_relations.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        $decodedMeilisearchContent = json_decode($meilisearchContent);

        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');

        // @extensionScannerIgnoreLine
        $category_stringM = $decodedMeilisearchContent->response->docs[0]->category_stringM;
        self::assertSame(['the category', 'the second category'], $category_stringM, 'Unexpected category_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategoryId_stringM = $decodedMeilisearchContent->response->docs[0]->sysCategoryId_stringM;
        self::assertSame(['1', '2'], $sysCategoryId_stringM, 'Unexpected sysCategoryId_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategory_stringM = $decodedMeilisearchContent->response->docs[0]->sysCategory_stringM;
        self::assertSame(['sys_category', 'sys_category 2'], $sysCategory_stringM, 'Unexpected sysCategory_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategoryDescription_stringM = $decodedMeilisearchContent->response->docs[0]->sysCategoryDescription_stringM;
        self::assertSame(['sys_category description', 'second sys_category description'], $sysCategoryDescription_stringM, 'Unexpected sysCategoryDescription_stringM value');

        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     * and could be limited with an additionalWhere clause at the same time
     *
     * @test
     */
    public function canIndexItemWithDirectRelationAndAdditionalWhere(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_direct_relationAndAdditionalWhere.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"category_stringM":["another category"]', $meilisearchContent, 'Did not find direct related category');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canUseConfigurationFromTemplateInRootLine(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_with_configuration_in_rootline.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"fieldFromRootLine_stringS":"TESTNEWS"', $meilisearchContent, 'Did not find field configured in rootline');
        self::assertStringContainsString('"title":"testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsViaPsr14EventListener(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sites_setup_and_data_set/01_integration_tree_one.csv');
        $document = new Document();
        $document->setField('original-document', true);
        $metaData = ['item_type' => 'pages', 'root' => 1];
        $record = ['uid' => 1, 'pid' => 0, 'activate-event-listener' => true];
        $item = new Item($metaData, $record);

        $result = $this->callInaccessibleMethod($this->indexer, 'getAdditionalDocuments', $document, $item, 0);
        // Result contains two documents, one from the event listener and the original one above
        self::assertCount(2, $result);
        self::assertSame($document, $result[0]);
        self::assertEquals(['can-be-an-alternative-record' => 'additional-test-document'], $result[1]->getFields());
    }

    /**
     * @test
     */
    public function testCanIndexCustomRecordOutsideOfSiteRoot(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_outside_site_root.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);

        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');

        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"title":"external testnews"', $meilisearchContent, 'Could not index document into meilisearch');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function testCanIndexCustomRecordOutsideOfSiteRootWithTemplate(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_outside_site_root_with_template.csv');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 1);

        self::assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $meilisearchContent, 'Could not index document into meilisearch');

        $meilisearchContent = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*&fq=site:testone.site');
        self::assertStringContainsString('"numFound":1', $meilisearchContent, 'Could not index document into meilisearch');
        self::assertStringContainsString('"url":"http://testone.site/en/"', $meilisearchContent, 'Item was indexed with false site UID');
        $this->cleanUpMeilisearchServerAndAssertEmpty();
    }

    protected function addToQueueAndIndexRecord(string $table, int $uid): bool
    {
        $result = false;
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid);

        // run the indexer
        $items = $this->indexQueue->getItems($table, $uid);
        foreach ($items as $item) {
            $result = $this->indexer->index($item);
        }

        return $result;
    }

    /**
     * @test
     */
    public function getMeilisearchConnectionsByItemReturnsNoDefaultConnectionIfRootPageIsHideDefaultLanguage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_rootPage_set_to_hide_default_language.csv');
        $itemMetaData = [
            'uid' => 1,
            'root' => 1,
            'item_type' => 'pages',
            'item_uid' => 1,
            'indexing_configuration' => '',
            'has_indexing_properties' => false,
        ];
        $item = new Item($itemMetaData);

        $result = $this->callInaccessibleMethod($this->indexer, 'getMeilisearchConnectionsByItem', $item);

        self::assertInstanceOf(MeilisearchConnection::class, $result[1], 'Expect MeilisearchConnection object in connection array item with key 1.');
        self::assertCount(1, $result, 'Expect only one SOLR connection.');
        self::assertArrayNotHasKey(0, $result, 'Expect, that there is no meilisearch connection returned for default language,');
    }

    /**
     * @test
     */
    public function getMeilisearchConnectionsByItemReturnsNoDefaultConnectionDefaultLanguageIsHiddenInSiteConfig(): void
    {
        $this->writeDefaultMeilisearchTestSiteConfigurationForHostAndPort('http', 'localhost', 8999, true);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_rootPage_set_to_hide_default_language.csv');
        $itemMetaData = [
            'uid' => 1,
            'root' => 1,
            'item_type' => 'pages',
            'item_uid' => 1,
            'indexing_configuration' => '',
            'has_indexing_properties' => false,
        ];
        $item = new Item($itemMetaData);

        $result = $this->callInaccessibleMethod($this->indexer, 'getMeilisearchConnectionsByItem', $item);

        self::assertEmpty($result[0], 'Connection for default language was expected to be empty');
        self::assertInstanceOf(MeilisearchConnection::class, $result[1], 'Expect MeilisearchConnection object in connection array item with key 1.');
        self::assertCount(1, $result, 'Expect only one SOLR connection.');
        self::assertArrayNotHasKey(0, $result, 'Expect, that there is no meilisearch connection returned for default language,');
    }

    /**
     * @test
     */
    public function getMeilisearchConnectionsByItemReturnsProperItemInNestedSite(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        $this->writeDefaultMeilisearchTestSiteConfigurationForHostAndPort();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_multiple_sites.csv');
        $result = $this->addToQueueAndIndexRecord('pages', 1);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $result = $this->addToQueueAndIndexRecord('pages', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $result = $this->addToQueueAndIndexRecord('pages', 120);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $this->waitToBeVisibleInMeilisearch();
        $meilisearchContentJson = file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/core_en/select?q=*:*');
        $meilisearchContent = json_decode($meilisearchContentJson, true);
        $meilisearchContentResponse = $meilisearchContent['response'];
        self::assertArrayHasKey('docs', $meilisearchContentResponse, 'Did not find docs in meilisearch response');

        $meilisearchDocs = $meilisearchContentResponse['docs'];
        self::assertCount(3, $meilisearchDocs, 'Could not found index document into meilisearch');

        $sites = array_column($meilisearchDocs, 'site');
        self::assertEquals('testone.site', $sites[0]);
        self::assertEquals('testtwo.site', $sites[1]);
        self::assertEquals('testtwo.site', $sites[2]);
    }
}
