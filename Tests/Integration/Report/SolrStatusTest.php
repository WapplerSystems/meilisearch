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

use WapplerSystems\Meilisearch\Report\MeilisearchStatus;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the Meilisearch status report
 *
 * @author Timo Hund
 */
class MeilisearchStatusTest extends IntegrationTest
{
    /**
     * @test
     */
    public function allStatusChecksShouldBeOkForValidMeilisearchConnection(): void
    {
        $this->writeDefaultMeilisearchTestSiteConfiguration();

        $meilisearchStatus = GeneralUtility::makeInstance(MeilisearchStatus::class);
        $statusCollection = $meilisearchStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity(), 'Expected that all status objects should be ok');
        }
    }

    /**
     * @test
     */
    public function allStatusChecksShouldFailForInvalidMeilisearchConnection(): void
    {
        $this->writeDefaultMeilisearchTestSiteConfigurationForHostAndPort(null, 'invalid', 4711);

        $meilisearchStatus = GeneralUtility::makeInstance(MeilisearchStatus::class);
        $statusCollection = $meilisearchStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that all status objects should indicate an error');
        }
    }
}
