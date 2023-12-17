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

namespace WapplerSystems\Meilisearch\Tests\Integration\Task;

use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use WapplerSystems\Meilisearch\IndexQueue\Queue;
use WapplerSystems\Meilisearch\System\Records\Queue\EventQueueItemRepository;
use WapplerSystems\Meilisearch\Task\EventQueueWorkerTask;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;

/**
 * Test case to check if the scheduler task EventQueueWorkerTask can process
 * event queue entries
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class EventQueueWorkerTaskTest extends IntegrationTest
{
    protected array $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    protected EventQueueItemRepository $eventQueue;
    protected Queue $indexQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->eventQueue = GeneralUtility::makeInstance(EventQueueItemRepository::class);

        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extConf->set('meilisearch', ['monitoringType' => 1]);
    }

    /**
     * @test
     */
    public function canProcessEventQueueItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_process_event_queue.csv');
        $this->eventQueue->addEventToQueue(new RecordUpdatedEvent(1, 'tt_content'));

        /** @var EventQueueWorkerTask $task */
        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);

        /** @var Scheduler $scheduler */
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        $scheduler->executeTask($task);

        self::assertEquals(1, $this->indexQueue->getAllItemsCount());
        self::assertEmpty($this->eventQueue->getEventQueueItems(null, false));
    }

    /**
     * @test
     */
    public function canHandleErroneousEventQueueItems(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_handle_erroneous_event_queue_items.csv');

        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        $scheduler->executeTask($task);

        self::assertEquals(0, $this->indexQueue->getAllItemsCount());
        self::assertEmpty($this->eventQueue->getEventQueueItems());
        $queueItems = $this->eventQueue->getEventQueueItems(null, false);
        self::assertEquals(1, count($queueItems));
        self::assertEquals(1, $queueItems[0]['error']);
        self::assertNotEmpty($queueItems[0]['error_message']);
    }
}
