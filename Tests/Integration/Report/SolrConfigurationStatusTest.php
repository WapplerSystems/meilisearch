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

namespace WapplerSystems\Meilisearch\Tests\Integration\Report;

use WapplerSystems\Meilisearch\Report\MeilisearchConfigurationStatus;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the Meilisearch configuration status report
 *
 * @author Timo Schmidt
 */
class MeilisearchConfigurationStatusTest extends IntegrationTest
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetGreenReportAgainstTestServer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_green_meilisearch_configuration_status_report.csv');

        /** @var MeilisearchConfigurationStatus $meilisearchConfigurationStatus */
        $meilisearchConfigurationStatus = GeneralUtility::makeInstance(MeilisearchConfigurationStatus::class);
        $results = $meilisearchConfigurationStatus->getStatus();
        self::assertCount(2, $results);
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning root page configurations'
        );
        self::assertEquals(
            $results[1]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning index enable flags'
        );
    }

    /**
     * @test
     */
    public function canDetectMissingRootPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_detect_missing_rootpage.csv');

        /** @var MeilisearchConfigurationStatus $meilisearchConfigurationStatus */
        $meilisearchConfigurationStatus = GeneralUtility::makeInstance(MeilisearchConfigurationStatus::class);
        $results = $meilisearchConfigurationStatus->getStatus();

        self::assertCount(1, $results);

        $firstViolation = array_pop($results);
        self::assertStringContainsString('No sites', $firstViolation->getValue(), 'Did not get a no sites found violation');
    }

    /**
     * @test
     */
    public function canDetectIndexingDisabled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_detect_indexing_disabled.csv');

        /** @var MeilisearchConfigurationStatus $meilisearchConfigurationStatus   */
        $meilisearchConfigurationStatus = GeneralUtility::makeInstance(MeilisearchConfigurationStatus::class);
        $results = $meilisearchConfigurationStatus->getStatus();

        self::assertCount(2, $results, 'Two test status are expected to be returned.');
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations concerning root page configurations'
        );
        self::assertStringContainsString(
            'Indexing is disabled',
            $results[1]->getValue(),
            'Did not get an indexing disabled violation'
        );
    }
}
