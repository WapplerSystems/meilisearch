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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Sorting;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Sorting\SortingHelper;
use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Class SortingHelperTest
 */
class SortingHelperTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canGetSortFieldFromUrlParameter()
    {
        $sortConfiguration = [
            'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
            'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
            'type.' => ['field' => 'type', 'label' => 'Type'],
        ];
        $sorting = new SortingHelper($sortConfiguration);
        $sortField = $sorting->getSortFieldFromUrlParameter('title asc');
        self::assertSame('sortTitle asc', $sortField);

        $sortField = $sorting->getSortFieldFromUrlParameter('title desc');
        self::assertSame('sortTitle desc', $sortField);

        $sortField = $sorting->getSortFieldFromUrlParameter('title desc,type asc');
        self::assertSame('sortTitle desc, type asc', $sortField);
    }

    /**
     * @test
     */
    public function canThrowExceptionForUnconfiguredSorting()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No sorting configuration found for option name unconfigured');
        $sorting = new SortingHelper([]);
        $sorting->getSortFieldFromUrlParameter('unconfigured asc');
    }
}
