<?php

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\NodeCollection;

class TestFacet extends AbstractFacet
{
    /**
     * The implementation of this method should return a "flatten" collection of all items.
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return new NodeCollection();
    }
}
