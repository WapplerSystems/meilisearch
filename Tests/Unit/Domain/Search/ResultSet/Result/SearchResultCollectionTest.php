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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Result;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Grouping\Group;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Grouping\GroupCollection;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResultCollection;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Unit test case for the SearchResultCollection.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchResultCollectionTest extends SetUpUnitTestCase
{
    /**
     * @var SearchResultCollection
     */
    protected $searchResultCollection;

    protected function setUp(): void
    {
        $this->searchResultCollection = new SearchResultCollection();
        parent::setUp();
    }

    /**
     * @test
     */
    public function getHasGroupsReturnsFalseByDefault()
    {
        self::assertFalse($this->searchResultCollection->getHasGroups());
    }

    /**
     * @test
     */
    public function getHasGroupsReturnsTrueWhenGroupsExist()
    {
        $groupA = new Group('foo');
        $this->searchResultCollection->getGroups()->add($groupA);
        self::assertTrue($this->searchResultCollection->getHasGroups());
    }

    /**
     * @test
     */
    public function canSetAndGetGroupCollection()
    {
        $groupCollection = new GroupCollection();
        $this->searchResultCollection->setGroups($groupCollection);
        self::assertSame($groupCollection, $this->searchResultCollection->getGroups());
    }
}
