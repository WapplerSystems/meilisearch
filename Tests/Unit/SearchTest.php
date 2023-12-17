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

namespace WapplerSystems\Meilisearch\Tests\Unit;

use WapplerSystems\Meilisearch\Domain\Search\Query\SearchQuery;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchReadService;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use PHPUnit\Framework\MockObject\MockObject;

class SearchTest extends SetUpUnitTestCase
{
    /**
     * @var MeilisearchConnection
     */
    protected MeilisearchConnection|MockObject $meilisearchConnectionMock;

    /**
     * @var MeilisearchReadService|MockObject
     */
    protected MeilisearchReadService|MockObject $meilisearchReadServiceMock;

    /**
     * @var Search
     */
    protected Search $search;

    protected function setUp(): void
    {
        //        $this->meilisearchReadServiceMock = $this->createMock(MeilisearchReadService::class);
        $this->meilisearchReadServiceMock = $this->getMockBuilder(MeilisearchReadService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();

        $this->meilisearchConnectionMock = $this->createMock(MeilisearchConnection::class);
        $this->meilisearchConnectionMock->expects(self::any())->method('getReadService')->willReturn($this->meilisearchReadServiceMock);
        $this->search = new Search($this->meilisearchConnectionMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canPassLimit()
    {
        $query = new SearchQuery();
        $limit = 99;
        $this->meilisearchReadServiceMock->expects(self::once())->method('search')->willReturnCallback(
            function ($query) use ($limit) {
                $this->assertSame($limit, $query->getRows(), 'Unexpected limit was passed');
                return $this->createMock(ResponseAdapter::class);
            }
        );

        $this->search->search($query, 0, $limit);
    }

    /**
     * @test
     */
    public function canKeepLimitWhenNullWasPassedAsLimit()
    {
        $query = new SearchQuery();
        $limit = 99;
        $query->setRows($limit);

        $this->meilisearchReadServiceMock->expects(self::once())->method('search')->willReturnCallback(
            function ($query) use ($limit) {
                $this->assertSame($limit, $query->getRows(), 'Unexpected limit was passed');
                return $this->createMock(ResponseAdapter::class);
            }
        );

        $this->search->search($query, 0, null);
    }
}
