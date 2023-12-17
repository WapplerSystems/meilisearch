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

use WapplerSystems\Meilisearch\Report\AccessFilterPluginInstalledStatus;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the Meilisearch Access Filter status report
 *
 * @author Timo Hund
 */
class AccessFilterPluginInstalledStatusTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetGreenAccessFilterStatus(): void
    {
        /** @var AccessFilterPluginInstalledStatus $accessFilterStatus */
        $accessFilterStatus = GeneralUtility::makeInstance(AccessFilterPluginInstalledStatus::class);
        $results = $accessFilterStatus->getStatus();

        self::assertCount(1, $results);
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations against the test Meilisearch server '
        );
    }
}
