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

namespace WapplerSystems\Meilisearch\System\Meilisearch;

use Meilisearch\Client;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SchemaParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\StopWordParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SynonymParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchService;
use WapplerSystems\Meilisearch\Util;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Meilisearch Service Access
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MeilisearchConnection
{
    protected array $clientConfiguration;

    protected ?MeilisearchService $readService = null;

    protected TypoScriptConfiguration $configuration;

    protected ?SynonymParser $synonymParser = null;

    protected ?StopWordParser $stopWordParser = null;

    protected ?SchemaParser $schemaParser = null;

    protected ?MeilisearchLogManager $logger = null;

    protected ?Client $client = null;

    protected ClientInterface $psr7Client;
    protected RequestFactoryInterface $requestFactory;

    protected StreamFactoryInterface $streamFactory;

    protected EventDispatcherInterface $eventDispatcher;

    protected string $url;

    /**
     * Constructor
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        array $clientConfiguration = [],
        TypoScriptConfiguration  $configuration = null,
        SynonymParser            $synonymParser = null,
        StopWordParser           $stopWordParser = null,
        SchemaParser             $schemaParser = null,
        MeilisearchLogManager    $logManager = null,
        ClientInterface          $psr7Client = null,
        RequestFactoryInterface  $requestFactory = null,
        StreamFactoryInterface   $streamFactory = null,
        EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->clientConfiguration = $clientConfiguration;
        $this->configuration = $configuration ?? Util::getMeilisearchConfiguration();
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
        $this->psr7Client = $psr7Client ?? GeneralUtility::getContainer()->get(ClientInterface::class);
        $this->requestFactory = $requestFactory ?? GeneralUtility::getContainer()->get(RequestFactoryInterface::class);
        $this->streamFactory = $streamFactory ?? GeneralUtility::getContainer()->get(StreamFactoryInterface::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::getContainer()->get(EventDispatcherInterface::class);


        $this->url = ($this->clientConfiguration['scheme'] ?? 'http') . '://' . $this->clientConfiguration['host'] . ':' . $this->clientConfiguration['port'];
    }


    /**
     * Returns Meilisearch service
     */
    public function getService(): MeilisearchService
    {
        if ($this->readService === null) {
            $this->readService = $this->buildService();
        }

        return $this->readService;
    }

    /**
     * Builds and returns Meilisearch read service
     */
    protected function buildService(): MeilisearchService
    {
        $client = $this->getClient();
        return GeneralUtility::makeInstance(MeilisearchService::class, $this, $client);
    }


    protected function getClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }


        $client = new Client($this->url, $this->clientConfiguration['masterKey'] ?? null, $this->psr7Client);

        $this->client = $client;
        return $client;
    }

    public function getUrl()
    {
        return $this->url;
    }

}
