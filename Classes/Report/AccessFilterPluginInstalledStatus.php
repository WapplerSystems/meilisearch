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
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchAdminService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether the Access Filter Query Parser Plugin
 * is installed on the Meilisearch server.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AccessFilterPluginInstalledStatus extends AbstractMeilisearchStatus
{
    /**
     * Meilisearch Access Filter plugin version.
     *
     * Must be updated when changing the plugin.
     */
    public const RECOMMENDED_PLUGIN_VERSION = '6.0.0';

    /**
     * The plugin's Java class name.
     */
    public const PLUGIN_CLASS_NAME = 'org.typo3.meilisearch.search.AccessFilterQParserPlugin';

    /**
     * Compiles a collection of meilisearchconfig.xml checks against each configured
     * Meilisearch server. Only adds an entry if the Access Filter Query Parser Plugin
     * is not configured.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        $meilisearchConnections = GeneralUtility::makeInstance(ConnectionManager::class)->getAllConnections();

        foreach ($meilisearchConnections as $meilisearchConnection) {
            $adminService = $meilisearchConnection->getAdminService();
            if ($adminService->ping()) {
                $installationStatus = $this->checkPluginInstallationStatus($adminService);
                $versionStatus = $this->checkPluginVersion($adminService);

                if (!is_null($installationStatus)) {
                    $reports[] = $installationStatus;
                }

                if (!is_null($versionStatus)) {
                    $reports[] = $versionStatus;
                }
            }
        }

        if (empty($reports)) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Meilisearch Access Filter Plugin',
                'OK',
                'Meilisearch Access Filter Plugin is installed in at least version ' . self::RECOMMENDED_PLUGIN_VERSION,
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
        return 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_reports.xlf:status_meilisearch_access-filter';
    }

    /**
     * Checks whether the Meilisearch plugin is installed.
     */
    protected function checkPluginInstallationStatus(MeilisearchAdminService $adminService): ?Status
    {
        if ($this->isPluginInstalled($adminService)) {
            return null;
        }

        $variables = ['meilisearch' => $adminService, 'recommendedVersion' => self::RECOMMENDED_PLUGIN_VERSION];

        $report = $this->getRenderedReport('AccessFilterPluginInstalledStatusNotInstalled.html', $variables);
        return GeneralUtility::makeInstance(
            Status::class,
            'Meilisearch Access Filter Plugin',
            'Not Installed',
            $report,
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * Checks whether the Meilisearch plugin version is up-to-date.
     */
    protected function checkPluginVersion(MeilisearchAdminService $adminService): ?Status
    {
        if (!($this->isPluginInstalled($adminService) && $this->isPluginOutdated($adminService))) {
            return null;
        }

        $version = $this->getInstalledPluginVersion($adminService);
        $variables = ['meilisearch' => $adminService, 'installedVersion' => $version, 'recommendedVersion' => self::RECOMMENDED_PLUGIN_VERSION];
        $report = $this->getRenderedReport('AccessFilterPluginInstalledStatusIsOutDated.html', $variables);

        return GeneralUtility::makeInstance(
            Status::class,
            'Meilisearch Access Filter Plugin',
            'Outdated',
            $report,
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * Checks whether the Access Filter Query Parser Plugin is installed for
     * the given Meilisearch server instance.
     *
     * @return bool True if the plugin is installed, FALSE otherwise.
     */
    protected function isPluginInstalled(MeilisearchAdminService $adminService): bool
    {
        $accessFilterQueryParserPluginInstalled = false;

        $pluginsInformation = $adminService->getPluginsInformation();
        if (isset($pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME})) {
            $accessFilterQueryParserPluginInstalled = true;
        }

        return $accessFilterQueryParserPluginInstalled;
    }

    /**
     * Checks whether the installed plugin is current.
     *
     * @return bool True if the plugin is outdated, FALSE if it meets the current version recommendation.
     */
    protected function isPluginOutdated(MeilisearchAdminService $adminService): bool
    {
        $pluginVersion = $this->getInstalledPluginVersion($adminService);
        return version_compare($pluginVersion, self::RECOMMENDED_PLUGIN_VERSION, '<');
    }

    /**
     * Gets the version of the installed plugin.
     *
     * @return string The installed plugin's version number.
     */
    public function getInstalledPluginVersion(MeilisearchAdminService $adminService): string
    {
        $pluginsInformation = $adminService->getPluginsInformation();

        $description = $pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME}->description;
        $matches = [];
        preg_match_all('/.*\(Version: (?<version>[^\)]*)\)/ums', $description, $matches);
        $rawVersion = $matches['version'][0] ?? '';

        $explodedRawVersion = explode('-', $rawVersion);
        return $explodedRawVersion[0] ?? '';
    }
}
