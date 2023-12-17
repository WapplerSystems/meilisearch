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

use WapplerSystems\Meilisearch\Report\MeilisearchVersionStatus;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the Meilisearch version test
 *
 * @author Timo Hund
 */
class MeilisearchVersionStatusTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetAGreenMeilisearchConfigStatusAgainstTestServer(): void
    {
        /** @var MeilisearchVersionStatus $meilisearchVersionStatus */
        $meilisearchVersionStatus = GeneralUtility::makeInstance(MeilisearchVersionStatus::class);
        $results = $meilisearchVersionStatus->getStatus();
        self::assertCount(6, $results);
        self::assertEmpty(
            array_filter(
                $results,
                static fn(Status $status): bool => $status->getSeverity() !== ContextualFeedbackSeverity::OK
            ),
            'We expect to get no violations against the test Meilisearch server '
        );
    }
}
