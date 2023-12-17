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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Meilisearch;

use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SchemaParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\StopWordParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SynonymParser;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;

/**
 * Class MeilisearchConnectionTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class MeilisearchConnectionTest extends SetUpUnitTestCase
{
    /**
     * @param Endpoint|null $readEndpoint
     * @param Endpoint|null $writeEndpoint
     * @param TypoScriptConfiguration|null $configuration
     * @param SynonymParser|null $synonymParser
     * @param StopWordParser|null $stopWordParser
     * @param SchemaParser|null $schemaParser
     * @param MeilisearchLogManager|null $logManager
     * @param ClientInterface|null $psr7Client
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param EventDispatcherInterface|null $eventDispatcher
     * @return MeilisearchConnection|null
     */
    protected function getMeilisearchConnectionWithDummyConstructorArgs(
        Endpoint                 $readEndpoint = null,
        Endpoint                 $writeEndpoint = null,
        TypoScriptConfiguration  $configuration = null,
        SynonymParser            $synonymParser = null,
        StopWordParser           $stopWordParser = null,
        SchemaParser             $schemaParser = null,
        MeilisearchLogManager    $logManager = null,
        ClientInterface          $psr7Client = null,
        RequestFactoryInterface  $requestFactory = null,
        StreamFactoryInterface   $streamFactory = null,
        EventDispatcherInterface $eventDispatcher = null
    ): ?MeilisearchConnection {
        try {
            return new MeilisearchConnection(
                $readEndpoint ?? $this->createMock(Endpoint::class),
                $writeEndpoint ?? $this->createMock(Endpoint::class),
                $configuration ?? $this->createMock(TypoScriptConfiguration::class),
                $synonymParser ?? $this->createMock(SynonymParser::class),
                $stopWordParser ?? $this->createMock(StopWordParser::class),
                $schemaParser ?? $this->createMock(SchemaParser::class),
                $logManager ?? $this->createMock(MeilisearchLogManager::class),
                $psr7Client ?? $this->createMock(ClientInterface::class),
                $requestFactory ?? $this->createMock(RequestFactoryInterface::class),
                $streamFactory ?? $this->createMock(StreamFactoryInterface::class),
                $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class)
            );
        } catch (\Throwable $e) {
            // No exception will be ever happen, this is for saving up the lines in test cases.
        }
        return null;
    }

    /**
     * @test
     */
    public function authenticationIsNotTriggeredWithoutUsername(): void
    {
        $endpointMock = $this->createMock(Endpoint::class);
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/meilisearch/', 'core' => 'core_en', 'scheme' => 'https', 'username' => '', 'password' => '']
        );
        $writeEndpoint = $readEndpoint;
        $connection = $this->getMeilisearchConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::never())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @test
     */
    public function authenticationIsTriggeredWhenUsernameIsPassed(): void
    {
        $endpointMock = $this->createMock(Endpoint::class);
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/meilisearch/', 'core' => 'core_en', 'scheme' => 'https', 'username' => 'foo', 'password' => 'bar']
        );
        $writeEndpoint = $readEndpoint;
        $connection = $this->getMeilisearchConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::once())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @return array
     */
    public function coreNameDataProvider(): array
    {
        return [
            ['path' => '/meilisearch/', 'core' => 'bla', 'expectedName' => 'bla'],
            ['path' => '/somewherelese/meilisearch/', 'core' => 'corename', 'expectedName' => 'corename'],
        ];
    }

    /**
     * @dataProvider coreNameDataProvider
     * @test
     */
    public function canGetCoreName(string $path, string $core, string $expectedCoreName): void
    {
        $fakeConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'core' => $core, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeEndpoint = $readEndpoint;
        $meilisearchService = $this->getMeilisearchConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint, $fakeConfiguration);
        self::assertSame($expectedCoreName, $meilisearchService->getService()->getPrimaryEndpoint()->getCore());
    }

    /**
     * @return array
     */
    public function coreBasePathDataProvider(): array
    {
        return [
            ['path' => '/', '' => 'bla', 'expectedPath' => ''],
            ['path' => '/somewherelese/', 'core' => 'corename', 'expectedCoreBasePath' => '/somewherelese'],
        ];
    }

    /**
     * @dataProvider coreBasePathDataProvider
     * @test
     */
    public function canGetCoreBasePath(string $path, string $core, string $expectedCoreBasePath): void
    {
        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'core' => $core, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeEndpoint = $readEndpoint;
        $meilisearchService = $this->getMeilisearchConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        self::assertSame($expectedCoreBasePath, $meilisearchService->getService()->getPrimaryEndpoint()->getPath());
    }

    /**
     * @test
     */
    public function coreBaseUriContainsAllSegments(): void
    {
        $readEndpoint = new Endpoint([
                'host' => 'localhost',
                'port' => 8080,
                'path' => '/mypath/',
                'core' => 'core_de',
                'scheme' => 'http',
                'username' => '',
                'password' => '',
        ]);
        $writeEndpoint = $readEndpoint;
        $meilisearchService = $this->getMeilisearchConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        self::assertSame(
            'http://localhost:8080/mypath/meilisearch/core_de/',
            $meilisearchService->getEndpoint('read')->getCoreBaseUri(),
            'Core base URI doesn\'t contain expected segments'
        );
    }
}
