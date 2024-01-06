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

namespace WapplerSystems\Meilisearch\Task;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Index\Queue\QueueInitializationService;
use Doctrine\DBAL\ConnectionException as DBALConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;

/**
 * Scheduler task to empty the indexes of a site and re-initialize the
 * Meilisearch Index Queue thus making the indexer re-index the site.
 *
 * @author Christoph Moeller <support@network-publishing.de>
 */
class ReIndexTask extends AbstractMeilisearchTask
{

    /**
     * Purges/commits all Meilisearch indexes, initializes the Index Queue
     * and returns TRUE if the execution was successful
     *
     * @return bool Returns TRUE on success, FALSE on failure.
     *
     * @throws DBALConnectionException
     * @throws DBALException
     *
     * @noinspection PhpMissingReturnTypeInspection See {@link \TYPO3\CMS\Scheduler\Task\AbstractTask::execute()}
     */
    public function execute()
    {
        $cleanUpResult = $this->cleanUpIndex();

        return true;
    }

    /**
     * Removes documents of the selected types from the index.
     *
     * @return bool TRUE if clean up was successful, FALSE on error
     *
     * @throws DBALException
     */
    protected function cleanUpIndex(): bool
    {
        $connections = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionsBySite($this->getSite());

        /** @var MeilisearchConnection $connection */
        foreach ($connections as $connection) {

            $client = $connection->getService()->getClient();

            $indexes = $client->getIndexes();
            foreach ($indexes as $index) {
                $client->deleteIndex($index->getUid());
            }

        }

        return true;
    }


}
