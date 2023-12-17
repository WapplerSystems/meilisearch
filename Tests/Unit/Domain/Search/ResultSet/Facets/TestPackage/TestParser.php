<?php

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;

class TestParser extends AbstractFacetParser
{
    public function parse(SearchResultSet $resultSet, string $facetName, array $facetConfiguration): ?AbstractFacet
    {
        return new TestFacet($resultSet, $facetName, 'testField');
    }
}
