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

use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SchemaParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\StopWordParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SynonymParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchAdminService;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchReadService;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchWriteService;
use WapplerSystems\Meilisearch\Util;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use Solarium\Core\Client\Endpoint;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Meilisearch Service Access
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MeilisearchConnection
{
    protected ?MeilisearchAdminService $adminService = null;

    protected ?MeilisearchReadService $readService = null;

    protected ?MeilisearchWriteService $writeService = null;

    protected TypoScriptConfiguration $configuration;

    protected ?SynonymParser $synonymParser = null;

    protected ?StopWordParser $stopWordParser = null;

    protected ?SchemaParser $schemaParser = null;

    /**
     * @var Endpoint[]
     */
    protected array $endpoints = [];

    protected ?MeilisearchLogManager $logger = null;

    /**
     * @var ClientInterface[]|Client[]
     */
    protected array $clients = [];

    protected ClientInterface $psr7Client;
    protected RequestFactoryInterface $requestFactory;

    protected StreamFactoryInterface $streamFactory;

    protected EventDispatcherInterface $eventDispatcher;

    /**
     * Constructor
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        Endpoint                 $readEndpoint,
        Endpoint                 $writeEndpoint,
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
        $this->endpoints['read'] = $readEndpoint;
        $this->endpoints['write'] = $writeEndpoint;
        $this->endpoints['admin'] = $writeEndpoint;
        $this->configuration = $configuration ?? Util::getMeilisearchConfiguration();
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
        $this->psr7Client = $psr7Client ?? GeneralUtility::getContainer()->get(ClientInterface::class);
        $this->requestFactory = $requestFactory ?? GeneralUtility::getContainer()->get(RequestFactoryInterface::class);
        $this->streamFactory = $streamFactory ?? GeneralUtility::getContainer()->get(StreamFactoryInterface::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
    }

    /**
     * Returns Endpoint by key
     */
    public function getEndpoint(string $key): Endpoint
    {
        return $this->endpoints[$key];
    }

    /**
     * Returns Meilisearch admin service
     */
    public function getAdminService(): MeilisearchAdminService
    {
        if ($this->adminService === null) {
            $this->adminService = $this->buildAdminService();
        }

        return $this->adminService;
    }

    /**
     * Builds and returns Meilisearch admin service
     */
    protected function buildAdminService(): MeilisearchAdminService
    {
        $endpointKey = 'admin';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(
            MeilisearchAdminService::class,
            $client,
            $this->configuration,
            $this->logger,
            $this->synonymParser,
            $this->stopWordParser,
            $this->schemaParser
        );
    }

    /**
     * Returns Meilisearch read service
     */
    public function getReadService(): MeilisearchReadService
    {
        if ($this->readService === null) {
            $this->readService = $this->buildReadService();
        }

        return $this->readService;
    }

    /**
     * Builds and returns Meilisearch read service
     */
    protected function buildReadService(): MeilisearchReadService
    {
        $endpointKey = 'read';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(MeilisearchReadService::class, $client);
    }

    /**
     * Returns Meilisearch write service
     */
    public function getWriteService(): MeilisearchWriteService
    {
        if ($this->writeService === null) {
            $this->writeService = $this->buildWriteService();
        }

        return $this->writeService;
    }

    /**
     * Builds and returns the Meilisearch write service
     */
    protected function buildWriteService(): MeilisearchWriteService
    {
        $endpointKey = 'write';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(MeilisearchWriteService::class, $client);
    }

    /**
     * Initializes client endpoint
     * Delegates auth credentials fom endpoint by key to given client if some present.
     */
    protected function initializeClient(Client $client, string $endpointKey): Client
    {
        $authentication = $this->getEndpoint($endpointKey)->getAuthentication();
        if (trim($authentication['username'] ?? '') === '') {
            return $client;
        }

        $this->setAuthenticationOnAllEndpoints($client, $authentication['username'], $authentication['password']);

        return $client;
    }

    /**
     * Sets auth credentials on all Meilisearch connection endpoints.
     */
    protected function setAuthenticationOnAllEndpoints(Client $client, string $username, string $password): void
    {
        foreach ($client->getEndpoints() as $endpoint) {
            $endpoint->setAuthentication($username, $password);
        }
    }

    /**
     * Returns Meilisearch client
     */
    protected function getClient(string $endpointKey): Client
    {
        if (isset($this->clients[$endpointKey])) {
            return $this->clients[$endpointKey];
        }

        $adapter = new Psr18Adapter($this->psr7Client, $this->requestFactory, $this->streamFactory);

        $client = new Client($adapter, $this->eventDispatcher);
        $client->getPlugin('postbigrequest');
        $client->clearEndpoints();

        $newEndpointOptions = $this->getEndpoint($endpointKey)->getOptions();
        $newEndpointOptions['key'] = $endpointKey;
        $client->createEndpoint($newEndpointOptions, true);

        $this->clients[$endpointKey] = $client;
        return $client;
    }

    public function setClient(Client $client, ?string $endpointKey = 'read'): void
    {
        $this->clients[$endpointKey] = $client;
    }
}
