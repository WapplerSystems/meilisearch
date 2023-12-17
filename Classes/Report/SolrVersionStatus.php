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
 * Provides a status report about whether the installed Meilisearch version matches
 * the required version.
 *
 * @author Stefan Sprenger <stefan.sprenger@dkd.de>
 */
class MeilisearchVersionStatus extends AbstractMeilisearchStatus
{
    /**
     * Required Meilisearch version. The version that gets installed when using the provided Docker image.
     */
    public const REQUIRED_SOLR_VERSION = '9.3.0';

    /**
     * Compiles a version check against each configured Meilisearch server.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        $solrConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            $coreAdmin = $solrConnection->getAdminService();
            /** @var MeilisearchConnection $solrConnection */
            if (!$coreAdmin->ping()) {
                $url = $coreAdmin->__toString();
                $pingFailedMsg = 'Could not ping Meilisearch server, can not check version ' . $url;
                $status = GeneralUtility::makeInstance(
                    Status::class,
                    'Apache Meilisearch Version',
                    'Not accessible',
                    $pingFailedMsg,
                    ContextualFeedbackSeverity::ERROR
                );
                $reports[] = $status;
                continue;
            }

            $solrVersion = $coreAdmin->getMeilisearchServerVersion();
            $isOutdatedVersion = version_compare($this->getCleanMeilisearchVersion($solrVersion), self::REQUIRED_SOLR_VERSION, '<');

            if (!$isOutdatedVersion) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    'Apache Meilisearch Version',
                    'OK',
                    'Version of ' . $coreAdmin->__toString() . ' is ok: ' . $solrVersion,
                    ContextualFeedbackSeverity::OK
                );
                continue;
            }

            $formattedVersion = $this->formatMeilisearchVersion($solrVersion);
            $variables = ['requiredVersion' => self::REQUIRED_SOLR_VERSION, 'currentVersion' => $formattedVersion, 'meilisearch' => $coreAdmin];
            $report = $this->getRenderedReport('MeilisearchVersionStatus.html', $variables);
            $status = GeneralUtility::makeInstance(
                Status::class,
                'Apache Meilisearch Version',
                'Outdated, Unsupported',
                $report,
                ContextualFeedbackSeverity::ERROR
            );

            $reports[] = $status;
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_reports.xlf:status_solr_solrversion';
    }

    /**
     * Gets the clean Meilisearch version in case of a custom build which may have
     * additional information in the version string.
     *
     * @param string $solrVersion Unformatted Apache Meilisearch version number a provided by Meilisearch.
     * @return string Clean Meilisearch version number: mayor.minor.patch-level
     */
    protected function getCleanMeilisearchVersion(string $solrVersion): string
    {
        $explodedMeilisearchVersion = explode('.', $solrVersion);

        return $explodedMeilisearchVersion[0]
            . '.' . $explodedMeilisearchVersion[1]
            . '.' . $explodedMeilisearchVersion[2];
    }

    /**
     * Formats the Apache Meilisearch server version number. By default, this is going
     * to be the simple major.minor.patch-level version. Custom Builds provide
     * more information though, in case of custom-builds, their complete
     * version will be added, too.
     *
     * @param string $solrVersion Unformatted Apache Meilisearch version number a provided by Meilisearch.
     * @return string formatted short version number, in case of custom-builds followed by the complete version number
     */
    protected function formatMeilisearchVersion(string $solrVersion): string
    {
        $shortMeilisearchVersion = $this->getCleanMeilisearchVersion($solrVersion);
        $formattedMeilisearchVersion = $shortMeilisearchVersion;

        if ($solrVersion != $shortMeilisearchVersion) {
            $formattedMeilisearchVersion .= ' (' . $solrVersion . ')';
        }

        return $formattedMeilisearchVersion;
    }
}
