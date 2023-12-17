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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\MeilisearchDocument;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Search\MeilisearchDocument\Repository;
use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test cases for MeilisearchDocumentRepository
 */
class RepositoryTest extends SetUpUnitTestCase
{
    /**
     * @var Search
     */
    protected $search;

    /**
     * @var ConnectionManager
     */
    protected $meilisearchConnectionManager;

    /**
     * @var Site
     */
    protected $mockedAsSingletonSite;

    /**
     * @test
     */
    public function findOneByPageIdAndByLanguageIdReturnsFirstFoundDocument()
    {
        $apacheMeilisearchDocumentCollection = [new Document(), new Document()];
        $apacheMeilisearchDocumentRepository = $this->getAccessibleMock(
            Repository::class,
            ['findByPageIdAndByLanguageId'],
            [],
            '',
            false
        );

        $apacheMeilisearchDocumentRepository
            ->expects(self::exactly(1))
            ->method('findByPageIdAndByLanguageId')
            ->willReturn($apacheMeilisearchDocumentCollection);

        /** @var Repository $apacheMeilisearchDocumentRepository */
        self::assertSame($apacheMeilisearchDocumentCollection[0], $apacheMeilisearchDocumentRepository->findOneByPageIdAndByLanguageId(0, 0));
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsEmptyCollectionIfConnectionToMeilisearchServerCanNotBeEstablished()
    {
        $apacheMeilisearchDocumentRepository = $this->getAccessibleMock(
            Repository::class,
            ['initializeSearch'],
            [],
            '',
            false
        );
        $apacheMeilisearchDocumentRepository
            ->expects(self::exactly(1))
            ->method('initializeSearch')
            ->will(self::throwException(new NoMeilisearchConnectionFoundException()));

        $apacheMeilisearchDocumentCollection = $apacheMeilisearchDocumentRepository->findByPageIdAndByLanguageId(777, 0);
        self::assertEmpty($apacheMeilisearchDocumentCollection);
    }

    /**
     * @test
     */
    public function findByPageIdAndByLanguageIdReturnsResultFromSearch()
    {
        $meilisearchConnectionMock = $this->createMock(MeilisearchConnection::class);
        $meilisearchConnectionManager = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $meilisearchConnectionManager->expects(self::any())->method('getConnectionByPageId')->willReturn($meilisearchConnectionMock);
        $mockedSingletons = [ConnectionManager::class => $meilisearchConnectionManager];

        $search = $this->getAccessibleMock(Search::class, ['search'], [], '', false);

        GeneralUtility::resetSingletonInstances($mockedSingletons);

        $testDocuments = [new Document(), new Document()];

        $parsedData = new \stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response = new \stdClass();
        // @extensionScannerIgnoreLine
        $parsedData->response->docs = $testDocuments;
        $fakeResponse = $this->createMock(ResponseAdapter::class);
        $fakeResponse->expects(self::once())->method('getParsedData')->willReturn($parsedData);
        $search->expects(self::any())->method('search')->willReturn($fakeResponse);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);

        $apacheMeilisearchDocumentRepository = $this->getAccessibleMock(Repository::class, ['getSearch'], [null, null, $queryBuilderMock]);
        $apacheMeilisearchDocumentRepository->expects(self::once())->method('getSearch')->willReturn($search);
        $queryMock = $this->createMock(Query::class);
        $queryBuilderMock->expects(self::any())->method('buildPageQuery')->willReturn($queryMock);
        $actualMeilisearchDocumentCollection = $apacheMeilisearchDocumentRepository->findByPageIdAndByLanguageId(777, 0);

        self::assertSame($testDocuments, $actualMeilisearchDocumentCollection);
    }
}
