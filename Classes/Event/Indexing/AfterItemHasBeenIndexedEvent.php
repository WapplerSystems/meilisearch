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

namespace WapplerSystems\Meilisearch\Event\Indexing;

use WapplerSystems\Meilisearch\Indexer\Item;
use WapplerSystems\Meilisearch\Task\IndexQueueWorkerTask;

/**
 * This event is dispatched after the indexing of an item
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
final class AfterItemHasBeenIndexedEvent
{
    private Item $item;

    private ?IndexQueueWorkerTask $task;

    private string $runId;

    public function __construct(Item $item, ?IndexQueueWorkerTask $task, string $runId)
    {
        $this->item = $item;
        $this->task = $task;
        $this->runId = $runId;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getTask(): ?IndexQueueWorkerTask
    {
        return $this->task;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }
}
