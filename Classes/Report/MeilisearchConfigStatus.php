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

namespace WapplerSystems\Meilisearch\Report;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about which meilisearchconfig version is used and checks
 * whether it fits the recommended version shipping with the extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MeilisearchConfigStatus extends AbstractMeilisearchStatus
{
    /**
     * The config name property is constructed as follows:
     *
     * tx_meilisearch    - The extension key
     * x-y-z    - The extension version this config is meant to work with
     * YYYYMMDD    - The date the config file was changed the last time
     *
     * Must be updated when changing the meilisearchconfig.
     */
    public const RECOMMENDED_MEILISEARCHCONFIG_VERSION = 'tx_meilisearch-12-0-0--20230602';

    /**
     * Compiles a collection of meilisearchconfig version checks against each configured
     * Meilisearch server. Only adds an entry if a meilisearchconfig other than the
     * recommended one was found.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        $meilisearchConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();
        if (empty($meilisearchConnections)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Meilisearchconfig Version',
                'No Meilisearch connections configured',
                '',
                ContextualFeedbackSeverity::WARNING
            );

            return $reports;
        }

        /** @var MeilisearchConnection $meilisearchConnection */
        foreach ($meilisearchConnections as $meilisearchConnection) {
            $service = $meilisearchConnection->getService();
            if (!$service->ping()) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    'Meilisearchconfig Version',
                    'Couldn\'t connect to ' . $service->__toString(),
                    '',
                    ContextualFeedbackSeverity::WARNING
                );

                continue;
            }

            if ($service->getMeilisearchconfigName() != self::RECOMMENDED_MEILISEARCHCONFIG_VERSION) {
                $variables = ['meilisearch' => $service, 'recommendedVersion' => self::RECOMMENDED_MEILISEARCHCONFIG_VERSION];
                $report = $this->getRenderedReport('MeilisearchConfigStatus.html', $variables);
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    'Meilisearchconfig Version',
                    'Unsupported meilisearchconfig.xml',
                    $report,
                    ContextualFeedbackSeverity::WARNING
                );

                $reports[] = $status;
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Meilisearchconfig Version',
                'OK',
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_reports.xlf:status_meilisearch_meilisearchconfig';
    }
}
