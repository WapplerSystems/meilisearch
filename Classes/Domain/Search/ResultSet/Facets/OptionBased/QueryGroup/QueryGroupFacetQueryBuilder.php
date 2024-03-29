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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\DefaultFacetQueryBuilder;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;

class QueryGroupFacetQueryBuilder extends DefaultFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * Builds group query parts for query group facet
     */
    public function build(string $facetName, TypoScriptConfiguration $configuration): array
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);
        foreach ($facetConfiguration['queryGroup.'] as $queryName => $queryConfiguration) {
            $tags = $this->buildExcludeTags($facetConfiguration, $configuration);
            $facetParameters['facet.query'][] = $tags . $facetConfiguration['field'] . ':' . $queryConfiguration['query'];
        }

        return $facetParameters;
    }
}
