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

namespace WapplerSystems\Meilisearch\Search;

use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\Statistics\StatisticsWriterProcessor;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchHasBeenExecutedEvent;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchQueryHasBeenPreparedEvent;

/**
 * Statistics search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class StatisticsComponent
{
    public function __construct(
        protected readonly QueryBuilder $queryBuilder,
        protected readonly StatisticsWriterProcessor $statisticsWriterProcessor
    ) {}

    /**
     * Enables the query's debug mode to get more detailed information.
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        if (!$event->getSearchRequest()->getContextTypoScriptConfiguration()->getStatistics()) {
            return;
        }
        // Only if addDebugData is enabled add Query modifier
        if (!$event->getSearchRequest()->getContextTypoScriptConfiguration()->getStatisticsAddDebugData()) {
            return;
        }

        $query = $event->getQuery();
        $query = $this->queryBuilder->startFrom($query)->useDebug(true)->getQuery();
        $event->setQuery($query);
    }

    public function writeStatisticsAfterSearch(AfterSearchHasBeenExecutedEvent $event): void
    {
        $meilisearchConfiguration = $event->getSearchRequest()->getContextTypoScriptConfiguration();

        if (!$meilisearchConfiguration->getStatistics()) {
            return;
        }
        // Write the statistics
        $this->statisticsWriterProcessor->process($event->getSearchResultSet());
    }
}
