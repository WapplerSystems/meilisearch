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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\Suggest;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\Query\SuggestQuery;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResult;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResultCollection;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSetService;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequest;
use WapplerSystems\Meilisearch\Domain\Search\Suggest\SuggestService;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SuggestServiceTest extends SetUpUnitTestCase
{
    protected SuggestService|MockObject $suggestService;
    protected TypoScriptFrontendController|MockObject $tsfeMock;
    protected SearchResultSetService|MockObject $searchResultSetServiceMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected QueryBuilder|MockObject $queryBuilderMock;
    protected SuggestQuery|MockObject $suggestQueryMock;

    protected function setUp(): void
    {
        $this->tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $this->searchResultSetServiceMock = $this->createMock(SearchResultSetService::class);
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);

        $this->suggestQueryMock = $this->createMock(SuggestQuery::class);
        $this->queryBuilderMock->expects(self::once())->method('buildSuggestQuery')->willReturn($this->suggestQueryMock);

        $this->suggestService = $this->getMockBuilder(SuggestService::class)
            ->onlyMethods(['getMeilisearchSuggestions'])
            ->setConstructorArgs([$this->tsfeMock, $this->searchResultSetServiceMock, $this->configurationMock, $this->queryBuilderMock])
            ->getMock();
        parent::setUp();
    }

    protected function assertSuggestQueryWithQueryStringCreated(string $queryString): void
    {
        $this->suggestQueryMock->expects(self::any())->method('getQuery')->willReturn($queryString);
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithoutTopResults(): void
    {
        // the query string is used as prefix but no real query string is passed.
        $this->assertSuggestQueryWithQueryStringCreated('');
        $fakeRequest = $this->getFakedSearchRequest('ty');

        $this->configurationMock->expects(self::once())->method('getSuggestShowTopResults')->willReturn(false);

        $this->assertNoSearchWillBeTriggered();

        $this->suggestService->expects(self::once())->method('getMeilisearchSuggestions')->willReturn([
            'type',
            'typo',
        ]);

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        $expectedSuggestions = [
            'suggestions' => ['type', 'typo'],
            'suggestion' => 'ty',
            'documents' => [],
            'didSecondSearch' => false,
        ];

        self::assertSame($expectedSuggestions, $suggestions, 'Suggest response did not contain expected content');
    }

    /**
     * @test
     */
    public function canHandleInvalidSyntaxInAdditionalFilters(): void
    {
        $this->assertNoSearchWillBeTriggered();
        $fakeRequest = $this->getFakedSearchRequest('some');

        $meilisearchConnectionMock = $this->createMock(MeilisearchConnection::class);
        $connectionManagerMock = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $connectionManagerMock->expects(self::any())->method('getConnectionByPageId')->willReturn($meilisearchConnectionMock);
        GeneralUtility::setSingletonInstance(ConnectionManager::class, $connectionManagerMock);

        GeneralUtility::setSingletonInstance(EventDispatcherInterface::class, new EventDispatcher($this->createMock(ListenerProviderInterface::class)));

        $searchStub = new class ($this->createMock(MeilisearchConnection::class)) extends Search implements SingletonInterface {
            public static SuggestServiceTest $suggestServiceTest;
            public function search(Query $query, $offset = 0, $limit = 10): ?ResponseAdapter
            {
                return self::$suggestServiceTest->getMockBuilder(ResponseAdapter::class)
                    ->onlyMethods([])->disableOriginalConstructor()->getMock();
            }
        };
        $searchStub::$suggestServiceTest = $this;
        GeneralUtility::setSingletonInstance(Search::class, $searchStub);

        $this->tsfeMock->expects(self::any())->method('getRequestedId')->willReturn(7411);
        $suggestService = new SuggestService(
            $this->tsfeMock,
            $this->searchResultSetServiceMock,
            $this->configurationMock,
            $this->queryBuilderMock
        );

        $suggestions = $suggestService->getSuggestions($fakeRequest);

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    /**
     * @test
     */
    public function emptyJsonIsReturnedWhenMeilisearchHasNoSuggestions(): void
    {
        $this->configurationMock->expects(self::never())->method('getSuggestShowTopResults');
        $this->assertNoSearchWillBeTriggered();

        $fakeRequest = $this->getFakedSearchRequest('ty');

        $this->suggestService->expects(self::once())->method('getMeilisearchSuggestions')->willReturn([]);
        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithTopResults(): void
    {
        $this->configurationMock->expects(self::once())->method('getSuggestShowTopResults')->willReturn(true);
        $this->configurationMock->expects(self::once())->method('getSuggestNumberOfTopResults')->willReturn(2);
        $this->configurationMock->expects(self::once())->method('getSuggestAdditionalTopResultsFields')->willReturn([]);

        $this->assertSuggestQueryWithQueryStringCreated('');
        $fakeRequest = $this->getFakedSearchRequest('type');
        $fakeRequest->expects(self::any())->method('getCopyForSubRequest')->willReturn($fakeRequest);

        $this->suggestService->expects(self::once())->method('getMeilisearchSuggestions')->willReturn([
            'type' => 25,
            'typo' => 5,
        ]);

        $fakeTopResults = $this->createMock(SearchResultSet::class);
        $fakeResultDocuments = new SearchResultCollection(
            [
                $this->getFakedSearchResult('http://www.typo3-meilisearch.com/a', 'pages', 'hello meilisearch', 'my suggestions'),
                $this->getFakedSearchResult('http://www.typo3-meilisearch.com/b', 'news', 'what new in meilisearch', 'new autosuggest'),
            ]
        );

        $fakeTopResults->expects(self::once())->method('getSearchResults')->willReturn($fakeResultDocuments);
        $this->searchResultSetServiceMock->expects(self::once())->method('search')->willReturn($fakeTopResults);

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        self::assertCount(2, $suggestions['documents'], 'Expected to have two top results');
        self::assertSame('pages', $suggestions['documents'][0]['type'], 'The first top result has an unexpected type');
        self::assertSame('news', $suggestions['documents'][1]['type'], 'The second top result has an unexpected type');
    }

    /**
     * Builds a faked SearchResult object.
     */
    protected function getFakedSearchResult(string $url, string $type, string $title, string $content): SearchResult|MockObject
    {
        $result = $this->createMock(SearchResult::class);
        $result->expects(self::once())->method('getUrl')->willReturn($url);
        $result->expects(self::once())->method('getType')->willReturn($type);
        $result->expects(self::once())->method('getTitle')->willReturn($title);
        $result->expects(self::once())->method('getContent')->willReturn($content);

        return $result;
    }

    protected function assertNoSearchWillBeTriggered(): void
    {
        $this->searchResultSetServiceMock->expects(self::never())->method('search');
    }

    protected function getFakedSearchRequest(string $queryString): SearchRequest|MockObject
    {
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::atLeastOnce())->method('getRawUserQuery')->willReturn($queryString);
        return $fakeRequest;
    }
}
