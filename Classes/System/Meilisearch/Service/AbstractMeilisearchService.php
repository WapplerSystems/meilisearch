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

use Meilisearch\Client;
use Meilisearch\Contracts\Endpoint;
use WapplerSystems\Meilisearch\PingFailedException;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\Util;
use Closure;
use Psr\Log\LogLevel;
use Throwable;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractMeilisearchService
{
    protected static array $pingCache = [];

    protected TypoScriptConfiguration $configuration;

    protected MeilisearchLogManager $logger;

    protected Client $client;

    protected MeilisearchConnection $connection;

    public function __construct(MeilisearchConnection $connection, Client $client, $typoScriptConfiguration = null, $logManager = null)
    {
        $this->connection = $connection;
        $this->client = $client;
        $this->configuration = $typoScriptConfiguration ?? Util::getMeilisearchConfiguration();
        $this->logger = $logManager ?? GeneralUtility::makeInstance(MeilisearchLogManager::class, __CLASS__);
    }


    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Return a valid http URL given this server's host, port and path and a provided servlet name
     */
    protected function _constructUrl(string $servlet, array $params = []): string
    {
        $queryString = count($params) ? '?' . http_build_query($params) : '';
        return $this->__toString() . $servlet . $queryString;
    }

    /**
     */
    public function __toString()
    {
        return $this->connection->getUrl();
    }

    /**
     * @return array<Endpoint>
     */
    public function getEndpoints(): array
    {
        return [];
    }



    /**
     * Build the log data and writes the message to the log
     */
    protected function writeLog(
        string           $logSeverity,
        string           $message,
        string           $url,
        ?ResponseAdapter $meilisearchResponse,
        Throwable        $exception = null,
        string           $contentSend = ''
    ): void
    {
        $logData = $this->buildLogDataFromResponse($meilisearchResponse, $exception, $url, $contentSend);
        $this->logger->log($logSeverity, $message, $logData);
    }

    /**
     * Parses the meilisearch information to build data for the logger.
     */
    protected function buildLogDataFromResponse(
        ResponseAdapter $meilisearchResponse,
        Throwable       $e = null,
        string          $url = '',
        string          $contentSend = ''
    ): array
    {
        $logData = ['query url' => $url, 'response' => (array)$meilisearchResponse];

        if ($contentSend !== '') {
            $logData['content'] = $contentSend;
        }

        if ($e !== null) {
            $logData['exception'] = $e->__toString();
            return $logData;
        }
        // trigger data parsing
        /** @noinspection PhpExpressionResultUnusedInspection */
        /** @phpstan-ignore-next-line */
        $meilisearchResponse->response;
        $logData['response data'] = print_r($meilisearchResponse, true);
        return $logData;
    }


    public function ping(): bool
    {

        return true;

    }

    /**
     * Call the /admin/ping servlet, can be used to get the runtime of a ping request.
     *
     * @param bool $useCache indicates if the ping result should be cached in the instance or not
     *
     * @return float runtime in milliseconds
     *
     * @throws PingFailedException
     */
    public function getPingRoundTripRuntime(bool $useCache = true): float
    {
        try {
            $start = $this->getMilliseconds();
            $httpResponse = $this->performPingRequest($useCache);
            $end = $this->getMilliseconds();
        } catch (HttpException $e) {
            throw new PingFailedException(
                'Meilisearch ping failed with unexpected response code: ' . $e->getCode(),
                1645716101
            );
        }

        if ($httpResponse->getHttpStatus() !== 200) {
            throw new PingFailedException(
                'Meilisearch ping failed with unexpected response code: ' . $httpResponse->getHttpStatus(),
                1645716102
            );
        }

        return $end - $start;
    }

    /**
     * Performs a ping request and returns the result.
     *
     * @param bool $useCache indicates if the ping result should be cached in the instance or not
     */
    protected function performPingRequest(bool $useCache = true): ResponseAdapter
    {
        $cacheKey = (string)($this);
        if ($useCache && isset(static::$pingCache[$cacheKey])) {
            return static::$pingCache[$cacheKey];
        }

        $pingQuery = $this->client->createPing();
        $pingResult = $this->createAndExecuteRequest($pingQuery);

        if ($useCache) {
            static::$pingCache[$cacheKey] = $pingResult;
        }

        return $pingResult;
    }

    /**
     * Returns the current time in milliseconds.
     */
    protected function getMilliseconds(): float
    {
        return round(microtime(true) * 1000);
    }

    /**
     * Creates and executes the request and returns the response
     */
    protected function createAndExecuteRequest(QueryInterface $query): ResponseAdapter
    {
        $request = $this->client->createRequest($query);
        return $this->executeRequest($request);
    }

    /**
     * Executes given request and returns the response
     */
    protected function executeRequest(Request $request): ResponseAdapter
    {
        try {
            $result = $this->client->executeRequest($request);
        } catch (HttpException $e) {
            return new ResponseAdapter($e->getMessage(), $e->getCode(), $e->getStatusMessage());
        }

        return new ResponseAdapter($result->getBody(), $result->getStatusCode(), $result->getStatusMessage());
    }


    /**
     * Build a relative path from base path to target path.
     * Required since Solarium contains the core information
     */
    protected function buildRelativePath(
        string $basePath,
        string $targetPath,
    ): string
    {
        $basePath = trim($basePath, '/');
        $targetPath = trim($targetPath, '/');
        $baseElements = explode('/', $basePath);
        $targetElements = explode('/', $targetPath);
        $targetSegment = array_pop($targetElements);
        foreach ($baseElements as $i => $segment) {
            if (isset($targetElements[$i]) && $segment === $targetElements[$i]) {
                unset($baseElements[$i], $targetElements[$i]);
            } else {
                break;
            }
        }
        $targetElements[] = $targetSegment;
        return str_repeat('../', count($baseElements)) . implode('/', $targetElements);
    }
}
