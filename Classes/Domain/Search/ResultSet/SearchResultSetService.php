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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet;

use WapplerSystems\Meilisearch\Domain\Search\Query\ParameterBuilder\QueryFields;
use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\Query\SearchQuery;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResult;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResultBuilder;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequest;
use WapplerSystems\Meilisearch\Domain\Variants\VariantsProcessor;
use WapplerSystems\Meilisearch\Event\Search\AfterInitialSearchResultSetHasBeenCreatedEvent;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchHasBeenExecutedEvent;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchIncompleteResponseException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * The SearchResultSetService is responsible to build a SearchResultSet from a SearchRequest.
 * It encapsulates the logic to trigger a search in order to be able to reuse it in multiple places.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSetService
{
    protected Search $search;

    protected ?SearchResultSet $lastResultSet = null;

    protected ?bool $isMeilisearchAvailable = null;

    protected TypoScriptConfiguration $typoScriptConfiguration;

    protected MeilisearchLogManager $logger;

    protected SearchResultBuilder $searchResultBuilder;

    protected QueryBuilder $queryBuilder;

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        TypoScriptConfiguration   $configuration,
        Search                    $search,
        ?MeilisearchLogManager    $meilisearchLogManager = null,
        ?SearchResultBuilder      $resultBuilder = null,
        ?QueryBuilder             $queryBuilder = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->search = $search;
        $this->typoScriptConfiguration = $configuration;
        $this->logger = $meilisearchLogManager ?? GeneralUtility::makeInstance(MeilisearchLogManager::class, __CLASS__);
        $this->searchResultBuilder = $resultBuilder ?? GeneralUtility::makeInstance(SearchResultBuilder::class);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, $configuration, $meilisearchLogManager);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    public function getIsMeilisearchAvailable(bool $useCache = true): bool
    {
        if ($this->isMeilisearchAvailable === null) {
            $this->isMeilisearchAvailable = $this->search->ping($useCache);
        }
        return $this->isMeilisearchAvailable;
    }

    /**
     * Retrieves the used search instance.
     */
    public function getSearch(): Search
    {
        return $this->search;
    }

    protected function getResultSetClassName(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultSetClassName '] ?? SearchResultSet::class;
    }

    /**
     * Performs a search and returns a SearchResultSet.
     *
     * @throws Facets\InvalidFacetPackageException
     */
    public function search(SearchRequest $searchRequest): SearchResultSet
    {
        $resultSet = $this->getInitializedSearchResultSet($searchRequest);
        $this->lastResultSet = $resultSet;

        $event = new AfterInitialSearchResultSetHasBeenCreatedEvent($resultSet, $searchRequest, $this->search, $this->typoScriptConfiguration);
        $event = $this->eventDispatcher->dispatch($event);
        $resultSet = $event->getSearchResultSet();
        if ($this->shouldReturnEmptyResultSetWithoutExecutedSearch($searchRequest)) {
            $resultSet->setHasSearched(false);
            return $resultSet;
        }

        $query = $this->queryBuilder->buildSearchQuery(
            $searchRequest->getRawUserQuery(),
            $searchRequest->getResultsPerPage(),
            $searchRequest->getAdditionalFilters()
        );

        $event = new AfterSearchQueryHasBeenPreparedEvent($query, $searchRequest, $this->search, $this->typoScriptConfiguration);
        $event = $this->eventDispatcher->dispatch($event);
        $query = $event->getQuery();

        $resultSet->setUsedQuery($query);
        // performing the actual search, sending the query to the Meilisearch server
        $response = $this->doASearch($query, $searchRequest);

        if ($searchRequest->getResultsPerPage() === 0) {
            // when resultPerPage was forced to 0 we also set the numFound to 0 to hide results, e.g.
            // when results for the initial search should not be shown.
            // @extensionScannerIgnoreLine
            $response->response->numFound = 0;
        }

        $resultSet->setHasSearched(true);
        $resultSet->setResponse($response);

        $this->getParsedSearchResults($resultSet);

        $resultSet->setUsedAdditionalFilters($this->queryBuilder->getAdditionalFilters());

        $variantsProcessor = GeneralUtility::makeInstance(
            VariantsProcessor::class,
            $this->typoScriptConfiguration,
            $this->searchResultBuilder
        );
        $variantsProcessor->process($resultSet);

        $searchResultReconstitutionProcessor = GeneralUtility::makeInstance(ResultSetReconstitutionProcessor::class);
        $searchResultReconstitutionProcessor->process($resultSet);

        $resultSet = $this->getAutoCorrection($resultSet);

        $event = new AfterSearchHasBeenExecutedEvent($resultSet, $query, $searchRequest, $this->search, $this->typoScriptConfiguration);
        $event = $this->eventDispatcher->dispatch($event);
        return $event->getSearchResultSet();
    }

    /**
     * Uses the configured parser and retrieves the parsed search results.
     */
    protected function getParsedSearchResults(SearchResultSet $resultSet): void
    {
        /** @var ResultParserRegistry $parserRegistry */
        $parserRegistry = GeneralUtility::makeInstance(ResultParserRegistry::class, $this->typoScriptConfiguration);
        $useRawDocuments = (bool)$this->typoScriptConfiguration->getValueByPathOrDefaultValue('plugin.tx_meilisearch.features.useRawDocuments', false);
        $parserRegistry->getParser($resultSet)->parse($resultSet, $useRawDocuments);
    }

    /**
     * Evaluates conditions on the request and configuration and returns true if no search should be triggered and an empty
     * SearchResultSet should be returned.
     */
    protected function shouldReturnEmptyResultSetWithoutExecutedSearch(SearchRequest $searchRequest): bool
    {
        if ($searchRequest->getRawUserQueryIsNull() && !$this->getInitialSearchIsConfigured()) {
            // when no rawQuery was passed or no initialSearch is configured, we pass an empty result set
            return true;
        }

        if ($searchRequest->getRawUserQueryIsEmptyString() && !$this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // the user entered an empty query string "" or "  " and empty querystring is not allowed
            return true;
        }

        return false;
    }

    /**
     * Initializes the SearchResultSet from the SearchRequest
     */
    protected function getInitializedSearchResultSet(SearchRequest $searchRequest): SearchResultSet
    {
        $resultSetClass = $this->getResultSetClassName();
        /** @var SearchResultSet $resultSet */
        $resultSet = GeneralUtility::makeInstance($resultSetClass);

        $resultSet->setUsedSearchRequest($searchRequest);
        $resultSet->setUsedPage((int)$searchRequest->getPage());
        $resultSet->setUsedResultsPerPage($searchRequest->getResultsPerPage());
        $resultSet->setUsedSearch($this->search);
        return $resultSet;
    }

    /**
     * Executes the search and builds a fake response for a current bug in Apache Meilisearch 6.3
     *
     * @throws MeilisearchIncompleteResponseException
     */
    protected function doASearch(Query $query, SearchRequest $searchRequest): ResponseAdapter
    {
        // the offset multiplier is page - 1 but not less than zero
        $offsetMultiplier = max(0, $searchRequest->getPage() - 1);
        $offSet = $offsetMultiplier * $searchRequest->getResultsPerPage();

        $response = $this->search->search($query, $offSet);
        if ($response === null) {
            throw new MeilisearchIncompleteResponseException('The response retrieved from meilisearch was incomplete', 1505989678);
        }

        return $response;
    }

    /**
     * Performs autocorrection if wanted
     *
     * @throws Facets\InvalidFacetPackageException
     */
    protected function getAutoCorrection(SearchResultSet $searchResultSet): SearchResultSet
    {
        // no secondary search configured
        if (!$this->typoScriptConfiguration->getSearchSpellcheckingSearchUsingSpellCheckerSuggestion()) {
            return $searchResultSet;
        }

        // if more as zero results
        if ($searchResultSet->getAllResultCount() > 0) {
            return $searchResultSet;
        }

        // no corrections present
        if (!$searchResultSet->getHasSpellCheckingSuggestions()) {
            return $searchResultSet;
        }

        return $this->performAutoCorrection($searchResultSet);
    }

    /**
     * Performs autocorrection
     *
     * @throws Facets\InvalidFacetPackageException
     */
    protected function performAutoCorrection(SearchResultSet $searchResultSet): SearchResultSet
    {
        $searchRequest = $searchResultSet->getUsedSearchRequest();
        $suggestions = $searchResultSet->getSpellCheckingSuggestions();

        $maximumRuns = $this->typoScriptConfiguration->getSearchSpellcheckingNumberOfSuggestionsToTry();
        $runs = 0;

        foreach ($suggestions as $suggestion) {
            $runs++;

            $correction = $suggestion->getSuggestion();
            $initialQuery = $searchRequest->getRawUserQuery();

            $searchRequest->setRawQueryString($correction);
            $searchResultSet = $this->search($searchRequest);
            if ($searchResultSet->getAllResultCount() > 0) {
                $searchResultSet->setIsAutoCorrected(true);
                $searchResultSet->setCorrectedQueryString($correction);
                $searchResultSet->setInitialQueryString($initialQuery);
                break;
            }

            if ($runs > $maximumRuns) {
                break;
            }
        }
        return $searchResultSet;
    }

    /**
     * Retrieves a single document from meilisearch by document id.
     */
    public function getDocumentById(string $documentId): SearchResult
    {
        /** @var SearchQuery $query */
        $query = $this->queryBuilder->newSearchQuery($documentId)->useQueryFields(QueryFields::fromString('id'))->getQuery();
        $response = $this->search->search($query, 0, 1);
        $parsedData = $response->getParsedData();
        // @extensionScannerIgnoreLine
        $resultDocument = $parsedData->response->docs[0] ?? null;

        if (!$resultDocument instanceof Document) {
            throw new UnexpectedValueException('Response did not contain a valid Document object');
        }

        return $this->searchResultBuilder->fromApacheMeilisearchDocument($resultDocument);
    }

    public function getLastResultSet(): ?SearchResultSet
    {
        return $this->lastResultSet;
    }

    /**
     * This method returns true when the last search was executed with an empty query
     * string or whitespaces only. When no search was triggered it will return false.
     */
    public function getLastSearchWasExecutedWithEmptyQueryString(): bool
    {
        $wasEmptyQueryString = false;
        if ($this->lastResultSet != null) {
            $wasEmptyQueryString = $this->lastResultSet->getUsedSearchRequest()->getRawUserQueryIsEmptyString();
        }

        return $wasEmptyQueryString;
    }

    protected function getInitialSearchIsConfigured(): bool
    {
        return $this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchShowResultsOfInitialEmptyQuery() || $this->typoScriptConfiguration->getSearchInitializeWithQuery() || $this->typoScriptConfiguration->getSearchShowResultsOfInitialQuery();
    }
}
