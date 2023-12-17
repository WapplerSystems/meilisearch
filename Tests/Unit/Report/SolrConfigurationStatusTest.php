<?php

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

namespace WapplerSystems\Meilisearch\Tests\Unit\Report;

use WapplerSystems\Meilisearch\Report\MeilisearchConfigurationStatus;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;

/**
 * Testcase for the MeilisearchConfigurationStatus class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class MeilisearchConfigurationStatusTest extends SetUpUnitTestCase
{
    protected MeilisearchConfigurationStatus|MockObject $report;

    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['meilisearch'] = [];
        // we mock the methods to external dependencies.

        $this->report = $this->getMockBuilder(MeilisearchConfigurationStatus::class)->onlyMethods(
            [
                'getRootPages',
                'getIsMeilisearchEnabled',
                'getIsIndexingEnabled',
                'getRenderedReport',
            ]
        )->getMock();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canGetEmptyResultWhenEverythingIsOK(): void
    {
        $fakedRootPages =  [1 => ['uid' => 1, 'title' => 'My Siteroot']];

        $this->report->expects(self::any())->method('getRootPages')->willReturn($fakedRootPages);

        $this->report->expects(self::any())->method('getIsMeilisearchEnabled')->willReturn(false);
        $this->report->expects(self::any())->method('getIsIndexingEnabled')->willReturn(false);

        // everything should be ok, so no report should be rendered
        $this->report->expects(self::never())->method('getRenderedReport');

        $this->report->getStatus();
    }

    /**
     * @test
     */
    public function canGetViolationWhenMeilisearchIsEnabledButIndexingNot(): void
    {
        $fakedRootPages =  [1 => ['uid' => 1, 'title' => 'My Siteroot']];

        $this->report->expects(self::any())->method('getRootPages')->willReturn($fakedRootPages);

        $this->report->expects(self::any())->method('getIsMeilisearchEnabled')->willReturn(true);
        $this->report->expects(self::any())->method('getIsIndexingEnabled')->willReturn(false);

        // one report should be rendered because meilisearch is enabled but indexing not
        $this->report->expects(self::once())->method('getRenderedReport')->with(
            'MeilisearchConfigurationStatusIndexing.html',
            ['pages' => [$fakedRootPages[1]]]
        )->willReturn('faked report output');

        $states = $this->report->getStatus();

        self::assertCount(2, $states, 'Expected to have two status reports');

        /** @var Status $firstState */
        $firstState = $states[0];
        self::assertSame(
            ContextualFeedbackSeverity::OK,
            $firstState->getSeverity(),
            'Expected to have no violation concerning root pages.'
        );

        $secondState = $states[1];
        self::assertSame(
            ContextualFeedbackSeverity::WARNING,
            $secondState->getSeverity(),
            'Expected to have one violation concerning page indexing flag.'
        );
    }
}
