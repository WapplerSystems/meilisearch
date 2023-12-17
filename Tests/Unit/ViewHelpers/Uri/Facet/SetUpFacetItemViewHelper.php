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

namespace WapplerSystems\Meilisearch\Tests\Unit\ViewHelpers\Uri\Facet;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequest;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class SetUpFacetItemViewHelper extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @return OptionsFacet
     */
    protected function getTestColorFacet()
    {
        $searchRequest = new SearchRequest();
        $searchResultSetMock = $this->createMock(SearchResultSet::class);
        $searchResultSetMock->expects(self::any())->method('getUsedSearchRequest')->willReturn($searchRequest);

        $facet = new OptionsFacet($searchResultSetMock, 'Color', 'color');
        $option = new Option($facet, 'Red', 'red', 4);
        $facet->addOption($option);

        return $facet;
    }
}
