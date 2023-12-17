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

namespace WapplerSystems\Meilisearch\Tests\Unit\Search;

use WapplerSystems\Meilisearch\Domain\Search\Query\Query;
use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequest;
use WapplerSystems\Meilisearch\Domain\Site\SiteHashService;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\Search\SortingComponent;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testcase for SortingComponent
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SortingComponentTest extends SetUpUnitTestCase
{
    protected Query|MockObject $query;
    protected SearchRequest|MockObject $searchRequestMock;
    protected SortingComponent|MockObject $sortingComponent;

    /**
     * SortingComponentTest constructor.
     */
    protected function setUp(): void
    {
        $this->query = new Query();
        $this->query->setQuery('');
        $this->searchRequestMock = $this->createMock(SearchRequest::class);

        $queryBuilder = new QueryBuilder(
            $this->createMock(TypoScriptConfiguration::class),
            $this->createMock(MeilisearchLogManager::class),
            $this->createMock(SiteHashService::class)
        );

        $this->sortingComponent = new SortingComponent($queryBuilder);
        parent::setUp();
    }

    /**
     * @test
     */
    public function sortingFromUrlIsNotAppliedWhenSortingIsDisabled(): void
    {
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $this->createMock(TypoScriptConfiguration::class));
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->__invoke($event);
        self::assertSame([], $event->getQuery()->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function validSortingFromUrlIsApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['sortTitle' => 'asc'], $this->query->getSorts(), 'Sorting was not applied in the query as expected');
    }

    /**
     * @test
     */
    public function invalidSortingFromUrlIsNotApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title INVALID']);
        $this->sortingComponent->__invoke($event);
        self::assertSame([], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function sortByIsApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn([]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function urlSortingHasPrioriy(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['sortTitle' =>  'asc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function querySortingHasPriorityWhenSortingIsDisabled(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
            'sorting' => 0,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }
}
