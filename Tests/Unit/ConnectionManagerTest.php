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

namespace WapplerSystems\Meilisearch\Tests\Unit;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\Exception\InvalidConnectionException;
use WapplerSystems\Meilisearch\System\Configuration\ConfigurationManager;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Container;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * PHP Unit test for connection manager
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class ConnectionManagerTest extends SetUpUnitTestCase
{
    protected ConnectionManager|MockObject $connectionManager;
    protected MeilisearchLogManager|MockObject $logManagerMock;
    protected PagesRepository|MockObject $pageRepositoryMock;
    protected SiteRepository|MockObject $siteRepositoryMock;
    protected ConfigurationManager $configurationManager;

    /**
     * Set up the connection manager test
     */
    protected function setUp(): void
    {
        $TSFE = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE'] = $TSFE;

        $this->logManagerMock = $this->createMock(MeilisearchLogManager::class);
        $this->pageRepositoryMock = $this->createMock(PagesRepository::class);
        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);

        $this->configurationManager = new ConfigurationManager();
        $this->connectionManager = new ConnectionManager(
            $this->pageRepositoryMock,
            $this->siteRepositoryMock
        );

        $container = new Container();
        $container->set(ClientInterface::class, $this->createMock(ClientInterface::class));
        $container->set(RequestFactoryInterface::class, $this->createMock(RequestFactoryInterface::class));
        $container->set(StreamFactoryInterface::class, $this->createMock(StreamFactoryInterface::class));
        $container->set(EventDispatcherInterface::class, $this->createMock(EventDispatcherInterface::class));
        GeneralUtility::setContainer($container);

        parent::setUp();
    }

    /**
     * Provides data for the connection test
     */
    public function connectDataProvider(): Traversable
    {
        yield 'invalid' => [
            'scheme' => '',
            'host' => 'localhost',
            'port' => null,
            'path' => '',
            'core' => 'core_de',
            'expectsException' => true,
            'expectedConnectionString' => null,
        ];

        yield 'valid without path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/meilisearch/core_de/',
        ];

        yield 'valid with slash in path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '/' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/meilisearch/core_de/',
        ];

        yield 'valid connection with path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '/production/' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/production/meilisearch/core_de/',
        ];
    }

    /**
     * Tests the connect
     *
     * @dataProvider connectDataProvider
     * @test
     */
    public function canConnect(
        string $scheme,
        string $host,
        ?int $port,
        string $path,
        string $core,
        bool $expectsException,
        ?string $expectedConnectionString
    ): void {
        $exceptionOccurred = false;
        try {
            $configuration = [
                'read' => ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'path' => $path, 'core' => $core],
            ];
            $configuration['write'] = $configuration['read'];

            $meilisearchService = $this->connectionManager->getConnectionFromConfiguration($configuration);
            self::assertEquals($expectedConnectionString, $meilisearchService->getService()->__toString());
        } catch (InvalidConnectionException $exception) {
            $exceptionOccurred = true;
        }
        self::assertEquals($expectsException, $exceptionOccurred);
    }
}
