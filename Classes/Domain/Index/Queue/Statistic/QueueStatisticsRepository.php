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

namespace WapplerSystems\Meilisearch\Domain\Index\Queue\Statistic;

use WapplerSystems\Meilisearch\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;
use PDO;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException;

/**
 * Class QueueStatisticsRepository
 */
class QueueStatisticsRepository extends AbstractRepository
{
    protected string $table = 'tx_meilisearch_indexqueue_item';
    protected string $columnIndexed = 'indexed';
    protected string $columnIndexingConfiguration = 'indexing_configuration';
    protected string $columnChanged = 'changed';
    protected string $columnErrors = 'errors';
    protected string $columnRootpage = 'root';

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @throws DBALException
     */
    public function findOneByRootPidAndOptionalIndexingConfigurationName(
        int $rootPid,
        ?string $indexingConfigurationName = null
    ): QueueStatistic {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->add('select', vsprintf('(%s < %s) AS %s', [
                $queryBuilder->quoteIdentifier($this->columnIndexed),
                $queryBuilder->quoteIdentifier($this->columnChanged),
                $queryBuilder->quoteIdentifier('pending'),
            ]), true)
            ->add('select', vsprintf('(%s) AS %s', [
                $queryBuilder->expr()->notLike($this->columnErrors, $queryBuilder->createNamedParameter('')),
                $queryBuilder->quoteIdentifier('failed'),
            ]), true)
            ->add('select', $queryBuilder->expr()->count('*', 'count'), true)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq($this->columnRootpage, $queryBuilder->createNamedParameter($rootPid, PDO::PARAM_INT))
            )->groupBy('pending', 'failed');

        if (!empty($indexingConfigurationName)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    $this->columnIndexingConfiguration,
                    $queryBuilder->createNamedParameter($indexingConfigurationName)
                )
            );
        }

        return $this->buildQueueStatisticFromResultSet(
            $queryBuilder
                ->executeQuery()
                ->fetchAllAssociative()
        );
    }

    /**
     * Instantiates and fills QueueStatistic with values
     */
    protected function buildQueueStatisticFromResultSet(array $indexQueueStatisticResultSet): QueueStatistic
    {
        /** @var QueueStatistic $statistic */
        $statistic = GeneralUtility::makeInstance(QueueStatistic::class);
        foreach ($indexQueueStatisticResultSet as $row) {
            if ($row['failed'] == 1) {
                $statistic->setFailedCount((int)$row['count']);
            } elseif ($row['pending'] == 1) {
                $statistic->setPendingCount((int)$row['count']);
            } else {
                $statistic->setSuccessCount((int)$row['count']);
            }
        }

        return $statistic;
    }

    /**
     * Don't use this method.
     *
     * @throws UnsupportedMethodException
     */
    public function count(): int
    {
        throw new UnsupportedMethodException('Can not count the Index Queue Statistics.', 1504694750);
    }
}
