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

use WapplerSystems\Meilisearch\Domain\Search\Query\ExtractingQuery;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchWriteService;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the meilisearch write service is working as expected.
 *
 * @author Timo Hund
 * (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
 */
class MeilisearchWriteServiceTest extends IntegrationTest
{
    protected MeilisearchWriteService|MockObject $meilisearchWriteService;

    protected function setUp(): void
    {
        parent::setUp();

        // @todo: Drop manual initialization of meilisearch Connection and use provided EXT:Meilisearch API.
        $psr7Client = $this->get(ClientInterface::class);
        $requestFactory = $this->get(RequestFactoryInterface::class);
        $streamFactory = $this->get(StreamFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $adapter = new Psr18Adapter(
            $psr7Client,
            $requestFactory,
            $streamFactory
        );
        $client = new Client($adapter, $eventDispatcher);

        $client->clearEndpoints();
        $meilisearchConnectionInfo = $this->getMeilisearchConnectionInfo();
        $client->createEndpoint(['host' => $meilisearchConnectionInfo['host'], 'port' => $meilisearchConnectionInfo['port'], 'path' => '/', 'core' => 'core_en', 'key' => 'admin'], true);

        $this->meilisearchWriteService = GeneralUtility::makeInstance(MeilisearchWriteService::class, $client);
    }

    /**
     * @test
     */
    public function canExtractByQuery(): void
    {
        $testFilePath = __DIR__ . '/Fixtures/testpdf.pdf';
        $extractQuery = GeneralUtility::makeInstance(ExtractingQuery::class, $testFilePath);
        $extractQuery->setExtractOnly(true);
        $response = $this->meilisearchWriteService->extractByQuery($extractQuery);
        self::assertStringContainsString('PDF Test', $response[0], 'Could not extract text');
    }
}
