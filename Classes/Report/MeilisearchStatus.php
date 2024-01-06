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
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\PingFailedException;
use Throwable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchService;

/**
 * Provides a status report about whether a connection to the Meilisearch server can
 * be established.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MeilisearchStatus extends AbstractMeilisearchStatus
{
    /**
     * Site Repository
     */
    protected SiteRepository $siteRepository;

    /**
     * Connection Manager
     */
    protected ConnectionManager $connectionManager;

    /**
     * Holds the response status
     */
    protected ContextualFeedbackSeverity $responseStatus = ContextualFeedbackSeverity::OK;

    /**
     * Holds the response message build by the checks
     */
    protected string $responseMessage = '';

    /**
     * MeilisearchStatus constructor.
     */
    public function __construct(SiteRepository $siteRepository = null, ConnectionManager $connectionManager = null)
    {
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->connectionManager = $connectionManager ?? GeneralUtility::makeInstance(ConnectionManager::class);
    }

    /**
     * Compiles a collection of status checks against each configured Meilisearch server.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllMeilisearchConnectionConfigurations() as $meilisearchConfiguration) {
                $reports[] = $this->getConnectionStatus($site, $meilisearchConfiguration);
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Meilisearch connection',
                'No Meilisearch connection configured',
                '',
                ContextualFeedbackSeverity::WARNING
            );
        }

        return $reports;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_reports.xlf:status_meilisearch_connectionstatus';
    }

    /**
     * Checks whether a Meilisearch server is available and provides some information.
     *
     * @param Site $site
     * @param array $meilisearchConnection Meilisearch connection parameters
     * @return Status Status of the Meilisearch connection
     */
    protected function getConnectionStatus(Site $site, array $meilisearchConnection): Status
    {
        $header = 'Your site has contacted the Meilisearch server.';
        $this->responseStatus = ContextualFeedbackSeverity::OK;

        $meilisearchAdmin = $this->connectionManager
            ->getMeilisearchConnection($meilisearchConnection)
            ->getService();

        $meilisearchVersion = $this->checkMeilisearchVersion($meilisearchAdmin);
        $accessFilter = $this->checkAccessFilter($meilisearchAdmin);
        $pingTime = $this->checkPingTime($meilisearchAdmin);
        $configName = $this->checkMeilisearchConfigName($meilisearchAdmin);
        $schemaName = $this->checkMeilisearchSchemaName($meilisearchAdmin);

        if ($this->responseStatus !== ContextualFeedbackSeverity::OK) {
            $header = 'Failed contacting the Meilisearch server.';
        }

        $variables = [
            'site' => $site->getLabel(),
            'siteLanguage' => $site->getTypo3SiteObject()->getLanguageById($meilisearchConnection['language']),
            'connection' => $meilisearchConnection,
            'meilisearch' => $meilisearchAdmin,
            'meilisearchVersion' => $meilisearchVersion,
            'pingTime' => $pingTime,
            'configName' => $configName,
            'schemaName' => $schemaName,
            'accessFilter' => $accessFilter,
        ];

        $report = $this->getRenderedReport('MeilisearchStatus.html', $variables);
        return GeneralUtility::makeInstance(
            Status::class,
            'Meilisearch Connection',
            $header,
            $report,
            $this->responseStatus
        );
    }

    /**
     * Checks the meilisearch version and adds it to the report.
     *
     * @return string meilisearch version
     */
    protected function checkMeilisearchVersion(MeilisearchService $meilisearch): string
    {
        try {
            $meilisearchVersion = $this->formatMeilisearchVersion($meilisearch->getMeilisearchServerVersion());
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $meilisearchVersion = 'Error getting meilisearch version: ' . $e->getMessage();
        }

        return $meilisearchVersion;
    }


    /**
     * Checks the ping time and adds it to the report.
     */
    protected function checkPingTime(MeilisearchService $meilisearchAdminService): string
    {
        try {
            $pingQueryTime = $meilisearchAdminService->getPingRoundTripRuntime();
            $pingMessage = (int)$pingQueryTime . ' ms';
        } catch (PingFailedException $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $pingMessage = 'Ping error: ' . $e->getMessage();
        }
        return $pingMessage;
    }

    /**
     * Checks the meilisearch config name and adds it to the report.
     */
    protected function checkMeilisearchConfigName(MeilisearchService $meilisearchAdminService): string
    {
        try {
            $meilisearchConfigMessage = $meilisearchAdminService->getMeilisearchconfigName();
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $meilisearchConfigMessage = 'Error determining meilisearch config: ' . $e->getMessage();
        }

        return $meilisearchConfigMessage;
    }

    /**
     * Checks the meilisearch schema name and adds it to the report.
     */
    protected function checkMeilisearchSchemaName(MeilisearchService $meilisearchAdminService): string
    {
        try {
            $meilisearchSchemaMessage = $meilisearchAdminService->getSchema()->getName();
        } catch (Throwable $e) {
            $this->responseStatus = ContextualFeedbackSeverity::ERROR;
            $meilisearchSchemaMessage = 'Error determining schema name: ' . $e->getMessage();
        }

        return $meilisearchSchemaMessage;
    }

    /**
     * Formats the Meilisearch server version number. By default, this is going
     * to be the simple major.minor.patch-level version. Custom Builds provide
     * more information though, in case of custom-builds, their complete
     * version will be added, too.
     *
     * @param string $meilisearchVersion Unformatted Meilisearch version number a provided by Meilisearch.
     * @return string formatted short version number, in case of custom-builds followed by the complete version number
     */
    protected function formatMeilisearchVersion(string $meilisearchVersion): string
    {
        $explodedMeilisearchVersion = explode('.', $meilisearchVersion);

        $shortMeilisearchVersion = $explodedMeilisearchVersion[0]
            . '.' . $explodedMeilisearchVersion[1]
            . '.' . $explodedMeilisearchVersion[2];

        $formattedMeilisearchVersion = $shortMeilisearchVersion;

        if ($meilisearchVersion != $shortMeilisearchVersion) {
            $formattedMeilisearchVersion .= ' (' . $meilisearchVersion . ')';
        }

        return $formattedMeilisearchVersion;
    }
}
