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

namespace WapplerSystems\Meilisearch\Tests\Unit\ViewHelpers\Document;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResult;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use WapplerSystems\Meilisearch\ViewHelpers\Document\RelevanceViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RelevanceViewHelperTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canCalculateRelevance()
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $resultSetMock->expects(self::any())->method('getMaximumScore')->willReturn(5.5);

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
        ];
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(10.0, $score, 'Unexpected score');
    }

    /**
     * @test
     */
    public function canCalculateRelevanceFromPassedMaximumScore()
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $resultSetMock->expects(self::never())->method('getMaximumScore');

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'maximumScore' => 11,
        ];
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(5.0, $score, 'Unexpected score');
    }
}
