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

namespace WapplerSystems\Meilisearch\Tests\Unit\IndexQueue;

use WapplerSystems\Meilisearch\Access\Rootline;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Index\PageIndexer\PageUriBuilder;
use WapplerSystems\Meilisearch\Domain\Search\MeilisearchDocument\Builder;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\Indexer\Item;
use WapplerSystems\Meilisearch\Indexer\PageIndexer;
use WapplerSystems\Meilisearch\Indexer\PageIndexerRequest;
use WapplerSystems\Meilisearch\Indexer\PageIndexerResponse;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;

class PageIndexerTest extends SetUpUnitTestCase
{
    protected PageIndexer|MockObject $pageIndexer;
    protected PagesRepository|MockObject $pagesRepositoryMock;
    protected Builder|MockObject $documentBuilderMock;
    protected MeilisearchLogManager|MockObject $meilisearchLogManagerMock;
    protected ConnectionManager|MockObject $connectionManagerMock;
    protected PageIndexerRequest|MockObject $pageIndexerRequestMock;
    protected PageUriBuilder|MockObject $uriBuilderMock;
    protected MockObject|FrontendEnvironment $frontendEnvironmentMock;

    protected function setUp(): void
    {
        $this->pagesRepositoryMock = $this->createMock(PagesRepository::class);
        $this->documentBuilderMock = $this->createMock(Builder::class);
        $this->meilisearchLogManagerMock = $this->createMock(MeilisearchLogManager::class);
        $this->connectionManagerMock = $this->createMock(ConnectionManager::class);
        $this->pageIndexerRequestMock = $this->createMock(PageIndexerRequest::class);
        $this->uriBuilderMock = $this->createMock(PageUriBuilder::class);
        $this->frontendEnvironmentMock = $this->createMock(FrontendEnvironment::class);
        parent::setUp();
    }

    protected function getPageIndexerWithMockedDependencies(array $options = []): PageIndexer|MockObject
    {
        $pageIndexer = $this->getMockBuilder(PageIndexer::class)
            ->setConstructorArgs(
                [
                    $options,
                    $this->pagesRepositoryMock,
                    $this->documentBuilderMock,
                    $this->connectionManagerMock,
                    $this->frontendEnvironmentMock,
                    $this->meilisearchLogManagerMock,
                    $this->createMock(EventDispatcherInterface::class),
                ]
            )
            ->onlyMethods(['getPageIndexerRequest', 'getAccessRootlineByPageId', 'getUriBuilder'])
            ->getMock();
        $pageIndexer->expects(self::any())->method('getPageIndexerRequest')->willReturn($this->pageIndexerRequestMock);
        $pageIndexer->expects(self::any())->method('getUriBuilder')->willReturn($this->uriBuilderMock);
        return $pageIndexer;
    }

    /**
     * @test
     */
    public function testIndexPageItemIsSendingFrontendRequestsToExpectedUrls(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['meilisearch'] = [];
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::once())->method('getAllMeilisearchConnectionConfigurations')->willReturn([
            ['rootPageUid' => 88, 'language' => 0],
        ]);

        $siteMock->expects(self::any())->method('getRootPageId')->willReturn(88);
        $siteMock->expects(self::any())->method('getRootPageRecord')->willReturn(['l18n_cfg' => 0, 'title' => 'mysiteroot']);

        $testUri = 'http://myfrontendurl.de/index.php?id=4711&L=0';
        $this->uriBuilderMock->expects(self::any())->method('getPageIndexingUriFromPageItemAndLanguageId')->willReturn($testUri);

        /** @var Item|MockObject $item */
        $item = $this->createMock(Item::class);
        $item->expects(self::any())->method('getRootPageUid')->willReturn(88);
        $item->expects(self::any())->method('getRecordUid')->willReturn(4711);
        $item->expects(self::any())->method('getSite')->willReturn($siteMock);
        $item->expects(self::any())->method('getIndexingConfigurationName')->willReturn('pages');

        $accessGroupResponse = $this->createMock(PageIndexerResponse::class);
        $accessGroupResponse->expects(self::once())->method('getActionResult')->with('findUserGroups')->willReturn([0]);

        $indexResponse = $this->createMock(PageIndexerResponse::class);
        $indexResponse->expects(self::once())->method('getActionResult')->with('indexPage')->willReturn(['pageIndexed' => 'Success']);

        // Two requests will be send, the first one for the access groups, the second one for the indexing itself
        $this->pageIndexerRequestMock->expects(self::exactly(2))->method('send')->with('http://myfrontendurl.de/index.php?id=4711&L=0')->will(
            self::onConsecutiveCalls($accessGroupResponse, $indexResponse)
        );

        $pageIndexer = $this->getPageIndexerWithMockedDependencies([]);
        $pageRootLineMock = $this->createMock(Rootline::class);
        $pageIndexer->expects(self::once())->method('getAccessRootlineByPageId')->willReturn($pageRootLineMock);

        $pageIndexer->index($item);
    }
}
