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

namespace WapplerSystems\Meilisearch\Tests\Integration\System\Meilisearch\Service;

use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchAdminService;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Curl;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the meilisearch admin service is working as expected.
 *
 * @author Timo Hund
 */
class MeilisearchAdminServiceTest extends IntegrationTest
{
    /**
     * @var MeilisearchAdminService
     */
    protected $meilisearchAdminService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $adapter = new Curl();
        $client = new Client(
            $adapter,
            $eventDispatcher
        );
        $client->clearEndpoints();
        $meilisearchConnectionInfo = $this->getMeilisearchConnectionInfo();
        $client->createEndpoint(['host' => $meilisearchConnectionInfo['host'], 'port' => $meilisearchConnectionInfo['port'], 'path' => '/', 'core' => 'core_en', 'key' => 'admin'], true);

        $this->meilisearchAdminService = GeneralUtility::makeInstance(MeilisearchAdminService::class, $client);
    }

    /**
     * @return array
     */
    public function synonymDataProvider()
    {
        return [
            'normal' => ['baseword' => 'homepage', 'synonyms' => ['website']],
            'umlaut' => ['baseword' => 'früher', 'synonyms' => ['vergangenheit']],
            '"' => ['baseword' => '"', 'synonyms' => ['quote mark']],
            '%' => ['baseword' => '%', 'synonyms' => ['percent']],
            '#' => ['baseword' => '#', 'synonyms' => ['hashtag']],
            ':' => ['baseword' => ':', 'synonyms' => ['colon']],
            ';' => ['baseword' => ';', 'synonyms' => ['semicolon']],
            // '/' still persists in https://issues.apache.org/jira/browse/SOLR-6853
            //'/' => ['baseword' => '/', 'synonyms' => ['slash']]
        ];
    }

    /**
     * @param string $baseWord
     * @param array $synonyms
     * @dataProvider synonymDataProvider
     * @test
     */
    public function canAddAndDeleteSynonym($baseWord, $synonyms = [])
    {
        $this->meilisearchAdminService->deleteSynonym($baseWord);
        $this->meilisearchAdminService->reloadCore();

        $synonymsBeforeAdd = $this->meilisearchAdminService->getSynonyms($baseWord);
        self::assertEquals([], $synonymsBeforeAdd, 'Synonyms was not empty');

        $this->meilisearchAdminService->addSynonym($baseWord, $synonyms);
        $this->meilisearchAdminService->reloadCore();

        $synonymsAfterAdd = $this->meilisearchAdminService->getSynonyms($baseWord);

        self::assertEquals($synonyms, $synonymsAfterAdd, 'Could not retrieve synonym after adding');

        $this->meilisearchAdminService->deleteSynonym($baseWord);
        $this->meilisearchAdminService->reloadCore();

        $synonymsAfterRemove = $this->meilisearchAdminService->getSynonyms($baseWord);
        self::assertEquals([], $synonymsAfterRemove, 'Synonym was not removed');
    }

    /**
     * @return array
     */
    public function stopWordDataProvider()
    {
        return [
            'normal' => ['stopword' => 'badword'],
            'umlaut' => ['stopword' => 'frühaufsteher'],
        ];
    }

    /**
     * @test
     * @dataProvider stopwordDataProvider
     */
    public function canAddStopWord($stopWord)
    {
        $stopWords = $this->meilisearchAdminService->getStopWords();

        self::assertNotContains($stopWord, $stopWords, 'Stopwords are not empty after initializing');

        $this->meilisearchAdminService->addStopWords($stopWord);
        $this->meilisearchAdminService->reloadCore();

        $stopWordsAfterAdd = $this->meilisearchAdminService->getStopWords();

        self::assertContains($stopWord, $stopWordsAfterAdd, 'Stopword was not added');

        $this->meilisearchAdminService->deleteStopWord($stopWord);
        $this->meilisearchAdminService->reloadCore();

        $stopWordsAfterDelete = $this->meilisearchAdminService->getStopWords();
        self::assertNotContains($stopWord, $stopWordsAfterDelete, 'Stopwords are not empty after removing');
    }

    /**
     * Check if the default stopswords are stored in the meilisearch server.
     *
     * @test
     */
    public function containsDefaultStopWord()
    {
        $stopWordsInMeilisearch = $this->meilisearchAdminService->getStopWords();
        self::assertContains('and', $stopWordsInMeilisearch, 'Default stopword and was not present');
    }

    /**
     * @test
     */
    public function canGetSystemInformation()
    {
        $informationResponse = $this->meilisearchAdminService->getSystemInformation();
        self::assertSame(200, $informationResponse->getHttpStatus(), 'Could not get information response from meilisearch server');
    }

    /**
     * @test
     */
    public function canGetPingRoundtrimRunTime()
    {
        $pingRuntime = $this->meilisearchAdminService->getPingRoundTripRuntime();
        self::assertGreaterThan(0, $pingRuntime, 'Ping runtime should be larger then 0');
        self::assertTrue(is_float($pingRuntime), 'Ping runtime should be an integer');
    }

    /**
     * @test
     */
    public function canGetMeilisearchServiceVersion()
    {
        $meilisearchServerVersion = $this->meilisearchAdminService->getMeilisearchServerVersion();
        $isVersionHigherSix = version_compare('6.0.0', $meilisearchServerVersion, '<');
        self::assertTrue($isVersionHigherSix, 'Expecting to run on version larger then 6.0.0');
    }

    /**
     * @test
     */
    public function canReloadCore()
    {
        $result = $this->meilisearchAdminService->reloadCore();
        self::assertSame(200, $result->getHttpStatus(), 'Reload core did not responde with a 200 ok status');
    }

    /**
     * @test
     */
    public function canGetPluginsInformation()
    {
        $result = $this->meilisearchAdminService->getPluginsInformation();
        self::assertSame(0, $result->responseHeader->status);
        self::assertSame(2, count($result));
    }

    /**
     * @test
     */
    public function canParseLanguageFromSchema()
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $adapter = new Curl();
        $client = new Client(
            $adapter,
            $eventDispatcher
        );
        $client->clearEndpoints();
        $meilisearchConnectionInfo = $this->getMeilisearchConnectionInfo();
        $client->createEndpoint(['host' => $meilisearchConnectionInfo['host'], 'port' => $meilisearchConnectionInfo['port'], 'path' => '/', 'core' => 'core_de', 'key' => 'admin'], true);

        $this->meilisearchAdminService = GeneralUtility::makeInstance(MeilisearchAdminService::class, $client);
        self::assertSame('core_de', $this->meilisearchAdminService->getSchema()->getManagedResourceId(), 'Could not get the id of managed resources from core.');
    }
}
