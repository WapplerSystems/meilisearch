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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\Option;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Unit test for the QueryGroupFacet options collection
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class OptionCollectionTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canGetManualSortedCopy()
    {
        $searchResultSetMock = $this->createMock(SearchResultSet::class);
        $facet = new QueryGroupFacet($searchResultSetMock, 'age', 'created');

        $week = new Option($facet, 'Last week', '1week', 9);
        $month = new Option($facet, 'Last month', '1month', 12);
        $year = new Option($facet, 'Last year', '1year', 3);

        $facet->addOption($week);
        $facet->addOption($month);
        $facet->addOption($year);

        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy(['1year', '1month']);

        self::assertSame($year, $sortedOptions->getByPosition(0), 'First sorted item was not 1year');
        self::assertSame($month, $sortedOptions->getByPosition(1), 'Second item was not 1month');
        self::assertSame($week, $sortedOptions->getByPosition(2), 'Third item was not 1week');
    }
}
