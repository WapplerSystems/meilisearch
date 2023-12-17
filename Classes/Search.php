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

namespace WapplerSystems\Meilisearch;

use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\DebugWriter;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchCommunicationException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use Doctrine\DBAL\Exception as DBALException;
use stdClass;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to handle solr search requests
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Search
{
    /**
     * An instance of the Meilisearch service
     */
    protected ?MeilisearchConnection $solr;

    /**
     * The search query
     */
    protected ?Query $query = null;

    /**
     * The search response
     */
    protected ?ResponseAdapter $response = null;

    protected TypoScriptConfiguration $configuration;

    protected MeilisearchLogManager $logger;

    /**
     * Search constructor
     *
     * @throws DBALException
     * @throws NoMeilisearchConnectionFoundException
     */
    public function __construct(MeilisearchConnection $solrConnection = null)
    {
        $this->logger = new MeilisearchLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));

        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $this->solr = $connectionManager->getConnectionByPageId(($GLOBALS['TSFE']->id ?? 0), ($GLOBALS['TSFE']?->getLanguage()->getLanguageId() ?? 0));
        }

        $this->configuration = Util::getMeilisearchConfiguration();
    }

    /**
     * Gets the Meilisearch connection used by this search.
     */
    public function getMeilisearchConnection(): ?MeilisearchConnection
    {
        return $this->solr;
    }

    /**
     * Sets the Meilisearch connection used by this search.
     *
     * Since WapplerSystems\Meilisearch\Search is a \TYPO3\CMS\Core\SingletonInterface, this is needed to
     * be able to switch between multiple cores/connections during
     * one request
     */
    public function setMeilisearchConnection(MeilisearchConnection $solrConnection): void
    {
        $this->solr = $solrConnection;
    }

    /**
     * Executes a query against a Meilisearch server.
     *
     * 1) Gets the query string
     * 2) Conducts the actual search
     * 3) Checks debug settings
     *
     * @param Query $query The query with keywords, filters, and so on.
     * @param int $offset Result offset for pagination.
     * @param int|null $limit Maximum number of results to return. If set to NULL, this value is taken from the query object.
     * @return ResponseAdapter|null Meilisearch response
     */
    public function search(Query $query, int $offset = 0, ?int $limit = null): ?ResponseAdapter
    {
        $this->query = $query;

        if (!empty($limit)) {
            $query->setRows($limit);
        }
        $query->setStart($offset);

        try {
            $response = $this->solr->getReadService()->search($query);
            if ($this->configuration->getLoggingQueryQueryString()) {
                $this->logger->info(
                    'Querying Meilisearch, getting result',
                    [
                        'query string' => $query->getQuery(),
                        'query parameters' => $query->getRequestBuilder()->build($query)->getParams(),
                        'response' => json_decode($response->getRawResponse(), true),
                    ]
                );
            }
        } catch (MeilisearchCommunicationException $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->error(
                    'Exception while querying Meilisearch',
                    [
                        'exception' => $e->__toString(),
                        'query' => (array)$query,
                        'offset' => $offset,
                        'limit' => $query->getRows(),
                    ]
                );
            }

            throw $e;
        }

        $this->response = $response;

        return $this->response;
    }

    /**
     * Sends a ping to the solr server to see whether it is available.
     */
    public function ping(bool $useCache = true): bool
    {
        $solrAvailable = false;

        try {
            if (!$this->solr->getReadService()->ping($useCache)) {
                throw new Exception('Meilisearch Server not responding.', 1237475791);
            }

            $solrAvailable = true;
        } catch (Throwable $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->error(
                    'Exception while trying to ping the solr server',
                    [
                        $e->__toString(),
                    ]
                );
            }
        }

        return $solrAvailable;
    }

    /**
     * Gets the query object.
     */
    public function getQuery(): ?Query
    {
        return $this->query;
    }

    /**
     * Gets the Meilisearch response
     */
    public function getResponse(): ?ResponseAdapter
    {
        return $this->response;
    }

    /**
     * Returns raw response if available.
     */
    public function getRawResponse(): ?string
    {
        return $this->response->getRawResponse();
    }

    /**
     * Returns response header if available.
     */
    public function getResponseHeader(): ?stdClass
    {
        return $this->getResponse()->responseHeader;
    }

    /**
     * Returns response body if available.
     */
    public function getResponseBody(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->getResponse()->response;
    }

    /**
     * Gets the time in milliseconds Meilisearch took to execute the query and return the result.
     */
    public function getQueryTime(): int
    {
        return $this->getResponseHeader()->QTime;
    }

    /**
     * Gets the number of results per page.
     */
    public function getResultsPerPage(): int
    {
        return $this->getResponseHeader()->params->rows;
    }

    /**
     * Gets the result offset.
     */
    public function getResultOffset(): int
    {
        // @extensionScannerIgnoreLine
        return $this->response->response->start;
    }

    /**
     * Returns the debug response if available.
     */
    public function getDebugResponse(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->response->debug;
    }

    /**
     * Returns highlighted content if available.
     */
    public function getHighlightedContent(): ?stdClass
    {
        $highlightedContent = new stdClass();

        if ($this->response->highlighting) {
            $highlightedContent = $this->response->highlighting;
        }

        return $highlightedContent;
    }
}
