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

namespace WapplerSystems\Meilisearch\Tests\Unit\Controller\Backend\Search;

use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexQueueModuleController;
use WapplerSystems\Meilisearch\Domain\Index\Queue\QueueItemRepository;
use WapplerSystems\Meilisearch\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use WapplerSystems\Meilisearch\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use WapplerSystems\Meilisearch\Domain\Index\Queue\Statistic\QueueStatisticsRepository;
use WapplerSystems\Meilisearch\Event\IndexQueue\AfterIndexQueueItemHasBeenMarkedForReindexingEvent;
use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\IndexQueue\Queue;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Tests\Unit\Fixtures\EventDispatcher\MockEventDispatcher;

/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexQueueModuleControllerTest extends AbstractModuleController
{
    protected Queue|MockObject $indexQueueMock;

    /**
     * @var IndexQueueModuleController|MockObject
     */
    protected $controller;

    protected MockEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUpConcreteModuleController(
            IndexQueueModuleController::class,
            ['addIndexQueueFlashMessage']
        );
        $this->eventDispatcher = new MockEventDispatcher();
        $this->indexQueueMock = $this->getMockBuilder(Queue::class)
            ->onlyMethods(['updateOrAddItemForAllRelatedRootPages'])
            ->setConstructorArgs([
                $this->createMock(RootPageResolver::class),
                $this->createMock(ConfigurationAwareRecordService::class),
                $this->createMock(QueueItemRepository::class),
                $this->createMock(QueueStatisticsRepository::class),
                $this->createMock(FrontendEnvironment::class),
                $this->eventDispatcher,
            ])
            ->getMock();
        $this->controller->setIndexQueue($this->indexQueueMock);
        parent::setUp();
    }

    protected function assertQueueUpdateIsTriggeredFor(string $type, int $uid): void
    {
        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->with($type, $uid)->willReturn(1);
    }

    /**
     * @test
     */
    public function requeueDocumentActionIsTriggeringReIndexOnIndexQueue(): void
    {
        $this->assertQueueUpdateIsTriggeredFor('pages', 4711);
        $this->controller->requeueDocumentAction('pages', 4711);
    }

    /**
     * @test
     */
    public function hookIsTriggeredWhenRegistered(): void
    {
        $this->eventDispatcher->addListener(function (AfterIndexQueueItemHasBeenMarkedForReindexingEvent $event) {
            $event->setUpdateCount(5);
        });

        $this->indexQueueMock->expects(self::once())->method('updateOrAddItemForAllRelatedRootPages')->willReturn(0);

        $this->assertQueueUpdateIsTriggeredFor('tx_meilisearch_file', 88);
        $this->controller->requeueDocumentAction('tx_meilisearch_file', 88);
    }
}
