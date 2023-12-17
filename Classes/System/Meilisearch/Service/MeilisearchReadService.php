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

namespace WapplerSystems\Meilisearch\System\Meilisearch\Service;

use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchCommunicationException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchInternalServerErrorException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchUnavailableException;
use RuntimeException;

/**
 * Class MeilisearchReadService
 */
class MeilisearchReadService extends AbstractMeilisearchService
{
    protected bool $hasSearched = false;

    protected ?ResponseAdapter $responseCache = null;

    /**
     * Performs a search.
     *
     * @return ResponseAdapter Meilisearch response
     * @throws RuntimeException if Meilisearch returns a HTTP status code other than 200
     */
    public function search(Query $query): ResponseAdapter
    {
        $request = $this->client->createRequest($query);
        $response = $this->executeRequest($request);

        if ($response->getHttpStatus() === 200) {
            $this->hasSearched = true;
            $this->responseCache = $response;
        } else {
            $this->handleErrorResponse($response);
        }
        return $response;
    }

    /**
     * Returns whether a search has been executed or not.
     *
     * @return bool TRUE if a search has been executed, FALSE otherwise
     */
    public function hasSearched(): bool
    {
        return $this->hasSearched;
    }

    /**
     * Gets the most recent response (if any)
     *
     * @return ResponseAdapter|null Most recent response, or NULL if a search has not been executed yet.
     */
    public function getResponse(): ?ResponseAdapter
    {
        return $this->responseCache;
    }

    /**
     * This method handles a failed Meilisearch request and maps it to a meaningful exception.
     *
     * @throws MeilisearchCommunicationException
     */
    protected function handleErrorResponse(ResponseAdapter $response): void
    {
        $status = $response->getHttpStatus();
        $message = $response->getHttpStatusMessage();

        if ($status === 0 || $status === 502) {
            $e = new MeilisearchUnavailableException('Meilisearch Server not available: ' . $message, 1505989391);
            $e->setMeilisearchResponse($response);
            throw $e;
        }

        if ($status === 500) {
            $e = new MeilisearchInternalServerErrorException('Internal Server error during search: ' . $message, 1505989897);
            $e->setMeilisearchResponse($response);
            throw $e;
        }

        $e = new MeilisearchCommunicationException('Invalid query. Meilisearch returned an error: ' . $status . ' ' . $message, 1293109870);
        $e->setMeilisearchResponse($response);

        throw $e;
    }
}
