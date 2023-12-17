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

use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\System\Configuration\ExtensionConfiguration;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use Doctrine\DBAL\Exception as DBALException;
use RuntimeException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report, which checks whether the configuration of the
 * extension is ok.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MeilisearchConfigurationStatus extends AbstractMeilisearchStatus
{
    protected ExtensionConfiguration $extensionConfiguration;

    protected FrontendEnvironment $frontendEnvironment;

    public function __construct(
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    ) {
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Compiles a collection of configuration status checks.
     *
     * @throws DBALException
     */
    public function getStatus(): array
    {
        $rootPageFlagStatus = $this->getRootPageFlagStatus();
        if ($rootPageFlagStatus->getSeverity() !== ContextualFeedbackSeverity::OK) {
            return [$rootPageFlagStatus];
        }

        return [
            $rootPageFlagStatus,
            $this->getConfigIndexEnableStatus(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_reports.xlf:status_meilisearch_configuration';
    }

    /**
     * Checks whether the "Use as Root Page" page property has been set for any site.
     *
     * @return Status An error status is returned if no root pages were found.
     *
     * @throws DBALException
     */
    protected function getRootPageFlagStatus(): Status
    {
        $rootPages = $this->getRootPages();
        if (!empty($rootPages)) {
            return GeneralUtility::makeInstance(
                Status::class,
                'Sites',
                'OK',
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        $report = $this->getRenderedReport('RootPageFlagStatus.html');
        return GeneralUtility::makeInstance(
            Status::class,
            'Sites',
            'No sites found',
            $report,
            ContextualFeedbackSeverity::ERROR
        );
    }

    /**
     * Checks whether config.index_enable is set to 1, otherwise indexing will
     * not work.
     *
     * @return Status An error status is returned for each site root page config.index_enable = 0.
     *
     * @throws DBALException
     */
    protected function getConfigIndexEnableStatus(): Status
    {
        $rootPagesWithIndexingOff = $this->getRootPagesWithIndexingOff();
        if (empty($rootPagesWithIndexingOff)) {
            return GeneralUtility::makeInstance(
                Status::class,
                'Page Indexing',
                'OK',
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        $report = $this->getRenderedReport('MeilisearchConfigurationStatusIndexing.html', ['pages' => $rootPagesWithIndexingOff]);
        return GeneralUtility::makeInstance(
            Status::class,
            'Page Indexing',
            'Indexing is disabled',
            $report,
            ContextualFeedbackSeverity::WARNING
        );
    }

    /**
     * Returns an array of rootPages where the indexing is off and EXT:meilisearch is enabled.
     *
     * @throws DBALException
     */
    protected function getRootPagesWithIndexingOff(): array
    {
        $rootPages = $this->getRootPages();
        $rootPagesWithIndexingOff = [];

        foreach ($rootPages as $rootPage) {
            try {
                $meilisearchIsEnabledAndIndexingDisabled = $this->getIsMeilisearchEnabled($rootPage['uid']) && !$this->getIsIndexingEnabled($rootPage['uid']);
                if ($meilisearchIsEnabledAndIndexingDisabled) {
                    $rootPagesWithIndexingOff[] = $rootPage;
                }
                /** @phpstan-ignore-next-line */
            } catch (RuntimeException) {
                $rootPagesWithIndexingOff[] = $rootPage;
                /** @phpstan-ignore-next-line */
            } catch (ServiceUnavailableException $sue) {
                if ($sue->getCode() == 1294587218) {
                    //  No TypoScript template found, continue with next site
                    $rootPagesWithIndexingOff[] = $rootPage;
                    continue;
                }
                /** @phpstan-ignore-next-line */
            } catch (SiteNotFoundException $sue) {
                if ($sue->getCode() == 1521716622) {
                    //  No site found, continue with next site
                    $rootPagesWithIndexingOff[] = $rootPage;
                    continue;
                }
            }
        }

        return $rootPagesWithIndexingOff;
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     *
     * @throws DBALException
     */
    protected function getRootPages(): array
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        return $pagesRepository->findAllRootPages();
    }

    /**
     * Checks if the meilisearch plugin is enabled with plugin.tx_meilisearch.enabled.
     *
     * @throws DBALException
     */
    protected function getIsMeilisearchEnabled(int $pageUid): bool
    {
        return $this->frontendEnvironment->getMeilisearchConfigurationFromPageId($pageUid)->getEnabled();
    }

    /**
     * Checks if the indexing is enabled with config.index_enable
     *
     * @throws DBALException
     */
    protected function getIsIndexingEnabled(int $pageUid): bool
    {
        return (bool)$this->frontendEnvironment
            ->getConfigurationFromPageId($pageUid)
            ->getValueByPathOrDefaultValue('config.index_enable', false);
    }
}
