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

namespace WapplerSystems\Meilisearch\Domain\Search\ApacheMeilisearchDocument;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchCommunicationException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Util;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Repository
 *
 * Purpose: TYPO3 BE INFO module :: Index Documents tab
 */
class Repository implements SingletonInterface
{
    protected ?Search $search = null;

    protected DocumentEscapeService $documentEscapeService;

    protected TypoScriptConfiguration $typoScriptConfiguration;

    protected QueryBuilder $queryBuilder;

    /**
     * Repository constructor.
     */
    public function __construct(
        DocumentEscapeService $documentEscapeService = null,
        TypoScriptConfiguration $typoScriptConfiguration = null,
        QueryBuilder $queryBuilder = null
    ) {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getMeilisearchConfiguration();
        $this->documentEscapeService = $documentEscapeService ?? GeneralUtility::makeInstance(DocumentEscapeService::class, $typoScriptConfiguration);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, $this->typoScriptConfiguration);
    }

    /**
     * Returns firs found {@link Document} for current page by given language id.
     *
     * @throws DBALException
     */
    public function findOneByPageIdAndByLanguageId($pageId, $languageId): Document|false
    {
        $documentCollection = $this->findByPageIdAndByLanguageId($pageId, $languageId);
        return reset($documentCollection);
    }

    /**
     * Returns all found \WapplerSystems\Meilisearch\System\Meilisearch\Document\Document[] by given page id and language id.
     * Returns empty array if nothing found, e.g. if no language or no page(or no index for page) is present.
     *
     * @return Document[]
     *
     * @throws DBALException
     */
    public function findByPageIdAndByLanguageId(int $pageId, int $languageId): array
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $pageQuery = $this->queryBuilder->buildPageQuery($pageId);
            $response = $this->search->search($pageQuery, 0, 10000);
        } catch (NoMeilisearchConnectionFoundException|MeilisearchCommunicationException) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * @return Document[]
     *
     * @throws DBALException
     */
    public function findByTypeAndPidAndUidAndLanguageId(
        string $type,
        int $uid,
        int $pageId,
        int $languageId
    ): array {
        try {
            $this->initializeSearch($pageId, $languageId);
            $recordQuery = $this->queryBuilder->buildRecordQuery($type, $uid, $pageId);
            $response = $this->search->search($recordQuery, 0, 10000);
        } catch (NoMeilisearchConnectionFoundException|MeilisearchCommunicationException) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * Initializes Search for given language
     *
     * @throws DBALException
     * @throws NoMeilisearchConnectionFoundException
     */
    protected function initializeSearch(int $pageId, int $languageId = 0): void
    {
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $meilisearchConnection = $connectionManager->getConnectionByPageId($pageId, $languageId);

        $this->search = $this->getSearch($meilisearchConnection);
    }

    /**
     * Retrieves an instance of the Search object.
     */
    protected function getSearch(MeilisearchConnection $meilisearchConnection): Search
    {
        return  GeneralUtility::makeInstance(Search::class, $meilisearchConnection);
    }
}
